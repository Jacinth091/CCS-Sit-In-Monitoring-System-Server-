<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/AiContextBuilder.php';
require_once '../../src/helpers/gemini.php';
require_once '../../src/helpers/AiCache.php';

require_once '../../src/middleware/AiAuthMiddleware.php';

$input = json_decode(file_get_contents("php://input"), true) ?? [];

// Guard the AI endpoint
$auth = AiAuthMiddleware::guard('student_insights', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

// Auth: Students only
$currentUser = requireStudent();
$studentId = $currentUser->student_id;

// ── 1. Cache check (per student, 12-hour TTL) ─────────────────────────────────
$cacheKey    = 'student_insights:' . $studentId;
$bypassCache = !empty($input['bypass_cache']);

if (!$bypassCache) {
    $cached = AiCache::get($db, $cacheKey);
    if ($cached) {
        sendSuccess(200, 'Student insights retrieved successfully', $cached, ['_cache' => true]);
        exit;
    }
}

// Sandbox Mode Check
if (!AI_ENABLED) {
    $sandboxResponse = [
        'summary' => "You have logged 12 hours across 8 completed sit-in sessions this semester, primarily using Lab 3 and Lab 5. You currently have 1 pending reservation for next week and 28 remaining credits.",
        'cards' => [
            [
                'title'       => 'Weekly Trend',
                'description' => 'Consistent study habits. Your sessions average 1.5 hours, peaking during early afternoon slots.',
                'type'        => 'stat',
                'value'       => '+15%'
            ],
            [
                'title'       => 'Off-Peak Hours',
                'description' => 'Lab 5 shows 40% lower occupancy between 1 PM and 3 PM. Ideal for quiet, uninterrupted work.',
                'type'        => 'idea',
                'value'       => 'Tip'
            ],
            [
                'title'       => 'Session Balance',
                'description' => 'You currently have ' . ($currentUser->session ?? '30') . ' remaining sit-in credits. Plan reservations accordingly.',
                'type'        => 'warning',
                'value'       => ($currentUser->session ?? '30') . ' Left'
            ]
        ]
    ];
    AiAuthMiddleware::logUsage($userId, $role, 'student_insights', $db);
    sendSuccess(200, 'Sandbox insights generated', $sandboxResponse, ['_sandbox' => true]);
}

// Live AI Inference
try {
    $contextBuilder = new AiContextBuilder($db);
    $context = $contextBuilder->forStudent($studentId);

    // ── Compute data fingerprint from key student metrics ─────────────────
    $fingerprint = AiCache::fingerprint([
        'credits'   => $context['profile']['session'] ?? 0,
        'sessions'  => $context['session_stats']['total_sessions'] ?? 0,
        'minutes'   => $context['session_stats']['total_minutes'] ?? 0,
        'recentCnt' => count($context['recent_sessions'] ?? []),
        'resCnt'    => count($context['upcoming_reservations'] ?? []),
    ]);

    // If bypass_cache was requested, check if data actually changed
    if ($bypassCache) {
        $fresh = AiCache::checkFreshness($db, $cacheKey, $fingerprint);
        if ($fresh) {
            sendSuccess(200, 'Data unchanged since last analysis. Returning cached insights.', $fresh, ['_cache' => true, '_data_unchanged' => true]);
            exit;
        }
    }

    $prompt = "You are an analytical assistant for a university computer laboratory monitoring system.\n";
    $prompt .= "Analyze this student's real-time system context and output EXACTLY a raw JSON object containing a student activity summary and exactly 3 insight cards.\n";
    $prompt .= "No markdown formatting, no conversational filler, no code blocks. Just valid JSON.\n\n";

    $prompt .= "## Student Data Context\n";
    $prompt .= "- Name: " . ($currentUser->first_name ?? '') . " " . ($currentUser->last_name ?? '') . "\n";
    $prompt .= "- Course Level: " . ($context['profile']['course_level'] ?? 'N/A') . " Year\n";
    $prompt .= "- Session Credits Remaining: " . ($context['profile']['session'] ?? '0') . " / 30\n";
    $prompt .= "- Total Sessions: " . ($context['session_stats']['total_sessions'] ?? '0') . "\n";
    $prompt .= "- Total Minutes Logged: " . ($context['session_stats']['total_minutes'] ?? '0') . "\n\n";

    if (!empty($context['recent_sessions'])) {
        $prompt .= "## Recent Sit-In Logs\n";
        foreach ($context['recent_sessions'] as $s) {
            $prompt .= "- Lab: " . $s['lab_code'] . ", Duration: " . $s['duration_minutes'] . " mins, Purpose: " . $s['purpose'] . ", Date: " . substr($s['time_in'], 0, 10) . "\n";
        }
        $prompt .= "\n";
    }

    if (!empty($context['upcoming_reservations'])) {
        $prompt .= "## Upcoming Reservations\n";
        foreach ($context['upcoming_reservations'] as $r) {
            $reservedTime12 = date('g:i A', strtotime($r['reserved_time']));
            $prompt .= "- Lab: " . $r['lab_code'] . ", PC: " . $r['pc_number'] . ", Date: " . $r['reserved_date'] . " at " . $reservedTime12 . " (" . $r['status'] . ")\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "Always format dates and times using the 12-hour AM/PM format (e.g. 2:30 PM, 10:15 AM) in all generated summary and card descriptions. Never use 24-hour time formatting (e.g. 14:30).\n\n";

    $prompt .= "\n## Instructions for output format:\n";
    $prompt .= "Generate a JSON object with exactly two keys: 'summary' and 'cards'.\n";
    $prompt .= "- 'summary': A comprehensive, encouraging, and informative narrative paragraph (30-65 words) summarizing their total hours tracked, session count, lab preferences, and status of upcoming reservations. Write it directly to the student in second person (you/your).\n";
    $prompt .= "- 'cards': A JSON array with exactly 3 objects. Each object MUST contain:\n";
    $prompt .= "  - 'title': Short header (max 4 words)\n";
    $prompt .= "  - 'description': Deep analytical description (max 20 words)\n";
    $prompt .= "  - 'type': Category string, MUST be exactly one of: 'stat', 'idea', or 'warning'\n";
    $prompt .= "  - 'value': Short status/metric text (max 8 characters)\n\n";
    $prompt .= "Example structure:\n";
    $prompt .= "{\n";
    $prompt .= "  \"summary\": \"You have completed 8 sit-in sessions totalling 12.5 hours, mainly utilizing Lab 5. With 28 credits remaining and a pending reservation next Tuesday, you are maintaining a consistent study routine.\",\n";
    $prompt .= "  \"cards\": [\n";
    $prompt .= "    {\"title\": \"Focused Pace\", \"description\": \"Average sit-in length is 90 mins, perfect for completing core programming laboratory assignments.\", \"type\": \"stat\", \"value\": \"90m avg\"},\n";
    $prompt .= "    {\"title\": \"Lab Traffic\", \"description\": \"Lab 5 is busiest at 10 AM. Rescheduling to 2 PM avoids seat queues.\", \"type\": \"idea\", \"value\": \"Tip\"},\n";
    $prompt .= "    {\"title\": \"Credit Alert\", \"description\": \"With 5 sessions left, consider applying for additional credits from CCS administration.\", \"type\": \"warning\", \"value\": \"5 Left\"}\n";
    $prompt .= "  ]\n";
    $prompt .= "}\n";

    $rawResponse = callGemini($prompt);

    if ($rawResponse === null) {
        $aiError = getLastGeminiError();
        if ($aiError && $aiError['http_code'] === 429) {
            // Rate limited! Fallback to sandbox/mock insights gracefully
            $sandboxResponse = [
                'summary' => "You have logged " . ($context['session_stats']['total_minutes'] ? round($context['session_stats']['total_minutes'] / 60, 1) : '12') . " hours across " . ($context['session_stats']['total_sessions'] ?? '8') . " completed sit-in sessions this semester. With " . ($context['profile']['session'] ?? '28') . " remaining credits, you are maintaining consistent lab progress.",
                'cards' => [
                    [
                        'title'       => 'Weekly Trend',
                        'description' => 'Consistent study habits. Your sessions peak during early afternoon slots.',
                        'type'        => 'stat',
                        'value'       => 'Active'
                    ],
                    [
                        'title'       => 'Off-Peak Hours',
                        'description' => 'Labs show lower occupancy between 1 PM and 3 PM. Ideal for quiet, uninterrupted work.',
                        'type'        => 'idea',
                        'value'       => 'Tip'
                    ],
                    [
                        'title'       => 'Session Balance',
                        'description' => 'You currently have ' . ($context['profile']['session'] ?? '28') . ' remaining sit-in credits.',
                        'type'        => 'warning',
                        'value'       => ($context['profile']['session'] ?? '28') . ' Left'
                    ]
                ],
                'is_fallback' => true,
                'fallback_reason' => 'Rate limit cooldown active'
            ];
            AiAuthMiddleware::logUsage($userId, $role, 'student_insights', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving local insights.', $sandboxResponse);
        }

        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to fetch insights from AI provider.', $isDev ? $aiError : null);
    }

    $cleanedJson = stripMarkdownFences($rawResponse);
    $insights = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($insights) || !isset($insights['cards'])) {
        error_log("Gemini JSON Parse Error on: " . $rawResponse);
        sendError(500, 'AI response format was invalid: ' . $rawResponse);
    }

    $insights['_generated_at'] = date('Y-m-d H:i:s');

    // Cache the successful result for 12 hours
    AiCache::set($db, $cacheKey, 'student_insights', $insights, 12.0, fingerprint: $fingerprint);

    AiAuthMiddleware::logUsage($userId, $role, 'student_insights', $db);
    sendSuccess(200, 'AI student insights retrieved successfully', $insights);

} catch (Exception $e) {
    sendError(500, 'Server error during insights extraction.', $e);
}
