<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/AiContextBuilder.php';
require_once '../../src/helpers/gemini.php';

require_once '../../src/middleware/AiAuthMiddleware.php';

$input = json_decode(file_get_contents("php://input"), true) ?? [];

// Guard the AI endpoint
$auth = AiAuthMiddleware::guard('admin_insights', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

// Auth: Admins only
$currentUser = requireAdmin();

// Sandbox Mode Check
if (!AI_ENABLED) {
    $sandboxCards = [
        [
            'title'       => 'Laboratory Peak',
            'description' => 'Lab 5 reaches 88% capacity during Tuesday mid-afternoon slots. Ensure lab staff coverage is planned.',
            'type'        => 'utilization',
            'value'       => '88% Peak'
        ],
        [
            'title'       => 'Shift Traffic',
            'description' => 'Promote Wednesday morning slots to balance student usage and relieve Friday afternoon congestion.',
            'type'        => 'recommendation',
            'value'       => 'Shift'
        ],
        [
            'title'       => 'Queue Backlog',
            'description' => 'There are pending lab reservations that have been sitting in the admin queue for over 24 hours.',
            'type'        => 'alert',
            'value'       => 'Action'
        ],
        [
            'title'       => 'Midterm Spike',
            'description' => 'System logs show a 24% week-over-week increase in sit-in sessions due to scheduled exams.',
            'type'        => 'trend',
            'value'       => '+24%'
        ]
    ];
    AiAuthMiddleware::logUsage($userId, $role, 'admin_insights', $db);
    sendSuccess(200, 'Sandbox insights generated', $sandboxCards, ['_sandbox' => true]);
}

// Live AI Inference
try {
    $contextBuilder = new AiContextBuilder($db);
    $context = $contextBuilder->forAdmin();

    $prompt = "You are a senior university operations strategist advising laboratory administrators. You have deep expertise in resource optimization, student behavioral analysis, and capacity planning.\n\n";
    $prompt .= "Your task: Analyze the following LIVE operational data and produce EXACTLY 4 strategic insight cards that an administrator CANNOT derive by simply looking at the numbers. Each insight must involve cross-correlation, trend prediction, or strategic recommendations.\n\n";
    $prompt .= "DO NOT just restate data counts (e.g. 'there are 8 pending reservations'). An admin can already see that. Instead, ANALYZE patterns, predict consequences, identify hidden inefficiencies, and recommend specific actions.\n\n";

    $prompt .= "## Live Operational Snapshot\n";
    $prompt .= "- Active sit-ins right now: " . $context['active_sessions_count'] . "\n";
    $prompt .= "- Pending reservations: " . $context['pending_reservations_count'] . "\n";
    $prompt .= "- Sessions today: " . $context['sessions_today'] . "\n";
    $prompt .= "- Sessions this week (Monday to now): " . $context['sessions_this_week'] . "\n";
    $prompt .= "- Sessions last week (Monday to the equivalent day/time): " . ($context['sessions_last_week'] ?? 0) . "\n";
    $prompt .= "  (IMPORTANT: The numbers for 'this week' and 'last week' are APPLES-TO-APPLES comparisons for the SAME duration into the week. If this week is much lower, it is a REAL drop, not just because the week hasn't finished yet.)\n";
    $prompt .= "- Average session duration: " . ($context['avg_session_duration_min'] ?? 0) . " minutes\n\n";

    // Week-over-week change
    $lastWeek = $context['sessions_last_week'] ?? 0;
    $thisWeek = $context['sessions_this_week'];
    if ($lastWeek > 0) {
        $wowChange = round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1);
        $prompt .= "- Week-over-week change: " . ($wowChange >= 0 ? '+' : '') . $wowChange . "%\n\n";
    }

    // Reservation pipeline
    $resStats = $context['reservation_stats_30d'] ?? [];
    if (!empty($resStats)) {
        $prompt .= "## Reservation Pipeline (Last 30 Days)\n";
        $prompt .= "- Approved: " . ($resStats['approved_count'] ?? 0) . ", Rejected: " . ($resStats['rejected_count'] ?? 0) . ", Pending: " . ($resStats['pending_count'] ?? 0) . ", Cancelled: " . ($resStats['cancelled_count'] ?? 0) . "\n";
        $totalRes = ($resStats['approved_count'] ?? 0) + ($resStats['rejected_count'] ?? 0) + ($resStats['pending_count'] ?? 0) + ($resStats['cancelled_count'] ?? 0);
        if ($totalRes > 0) {
            $approvalRate = round((($resStats['approved_count'] ?? 0) / $totalRes) * 100, 1);
            $prompt .= "- Approval rate: " . $approvalRate . "%\n\n";
        }
    }

    // Lab utilization with capacity context
    if (!empty($context['lab_utilization'])) {
        $prompt .= "## Lab Utilization (This Week)\n";
        foreach ($context['lab_utilization'] as $l) {
            $prompt .= "- " . $l['lab_code'] . " (" . $l['lab_name'] . "): " . $l['session_count'] . " sessions, " . $l['total_minutes'] . " total minutes\n";
        }
        $prompt .= "\n";
    }

    // Purpose distribution
    if (!empty($context['purpose_distribution'])) {
        $prompt .= "## Purpose Distribution (30 Days)\n";
        foreach ($context['purpose_distribution'] as $p) {
            $prompt .= "- " . $p['purpose'] . ": " . $p['count'] . " sessions\n";
        }
        $prompt .= "\n";
    }

    // Hourly patterns
    if (!empty($context['hourly_distribution'])) {
        $prompt .= "## Busiest Hours (14 Days)\n";
        foreach ($context['hourly_distribution'] as $h) {
            $hour = (int)$h['hour'];
            $ampm = $hour < 12 ? 'AM' : 'PM';
            $display = $hour === 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour);
            $prompt .= "- " . $display . ":00 " . $ampm . ": " . $h['count'] . " sessions\n";
        }
        $prompt .= "\n";
    }

    // Top students
    if (!empty($context['top_students_this_month'])) {
        $prompt .= "## Top Students This Month (by hours)\n";
        foreach ($context['top_students_this_month'] as $s) {
            $prompt .= "- " . $s['name'] . ": " . $s['total_minutes'] . " minutes\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "## Output Requirements\n";
    $prompt .= "Respond with a RAW JSON ARRAY (no markdown, no code blocks). Exactly 4 objects. Each must contain:\n";
    $prompt .= "- 'title': Strategic header (3-5 words, e.g. 'Capacity Rebalancing Needed', 'Schedule Optimization Window')\n";
    $prompt .= "- 'description': Cross-correlated analysis with a SPECIFIC actionable recommendation (15-30 words). Must reference actual data relationships, not just restate a single metric.\n";
    $prompt .= "- 'type': Exactly one of: 'utilization', 'recommendation', 'alert', 'trend'\n";
    $prompt .= "- 'value': A derived metric or insight indicator (max 10 chars, e.g. '+24% WoW', '3:1 Ratio', '~45min')\n\n";
    $prompt .= "GOOD examples: 'Lab 527 handles 60% of C Programming sessions but only has 30 PCs — consider routing overflow to Lab 525 which is 80% idle'\n";
    $prompt .= "BAD examples: 'There are 8 pending reservations' or 'Only 1 session today' — these are just data restating.\n";

    $rawResponse = callGemini($prompt);

    if ($rawResponse === null) {
        $aiError = getLastGeminiError();
        if ($aiError && $aiError['http_code'] === 429) {
            // Rate limited! Fallback to sandbox/mock cards gracefully
            $sandboxCards = [
                [
                    'title'       => 'Laboratory Peak',
                    'description' => 'Lab utilization peak averages 88% capacity during busy Tuesday mid-afternoon blocks.',
                    'type'        => 'utilization',
                    'value'       => '88% Peak'
                ],
                [
                    'title'       => 'Shift Traffic',
                    'description' => 'Promote Wednesday morning slots to balance student usage and relieve Friday afternoon congestion.',
                    'type'        => 'recommendation',
                    'value'       => 'Shift'
                ],
                [
                    'title'       => 'Queue Backlog',
                    'description' => 'There are ' . ($context['pending_reservations_count'] ?? '0') . ' pending reservations currently awaiting review.',
                    'type'        => 'alert',
                    'value'       => 'Action'
                ],
                [
                    'title'       => 'Weekly Trend',
                    'description' => 'System logs show ' . ($context['sessions_this_week'] ?? '0') . ' sessions tracked this week.',
                    'type'        => 'trend',
                    'value'       => 'Active'
                ]
            ];
            AiAuthMiddleware::logUsage($userId, $role, 'admin_insights', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving local insights.', $sandboxCards);
        }

        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to fetch insights from AI provider.', $isDev ? $aiError : null);
    }

    $cleanedJson = stripMarkdownFences($rawResponse);
    $insights = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($insights)) {
        error_log("Gemini Admin JSON Parse Error on: " . $rawResponse);
        sendError(500, 'AI response format was invalid. Please try again.');
    }

    AiAuthMiddleware::logUsage($userId, $role, 'admin_insights', $db);
    sendSuccess(200, 'AI admin insights retrieved successfully', $insights);

} catch (Exception $e) {
    sendError(500, 'Server error during admin insights generation.', $e);
}
