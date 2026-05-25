<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/AiContextBuilder.php';
require_once '../../src/helpers/gemini.php';
require_once '../../src/helpers/AiCache.php';
require_once '../../src/middleware/AiAuthMiddleware.php';

$input  = json_decode(file_get_contents("php://input"), true) ?? [];
$auth   = AiAuthMiddleware::guard('admin_insights', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

$currentUser = requireAdmin();

// ── 1. Cache check (6-hour TTL, shared across all admins) ────────────────────
$cacheKey     = 'admin_insights';
$bypassCache  = !empty($input['bypass_cache']);

if (!$bypassCache) {
    $cached = AiCache::get($db, $cacheKey);
    if ($cached) {
        sendSuccess(200, 'Admin insights retrieved', $cached, ['_cache' => true]);
        exit;
    }
}

// ── 2. Sandbox fallback ───────────────────────────────────────────────────────
if (!AI_ENABLED) {
    $sandbox = [
        'cards' => [
            ['title' => 'Laboratory Peak',      'description' => 'Lab 5 reaches 88% capacity during Tuesday mid-afternoon slots. Ensure lab staff coverage is planned.', 'type' => 'utilization',    'value' => '88% Peak'],
            ['title' => 'Shift Traffic',         'description' => 'Promote Wednesday morning slots to balance student usage and relieve Friday afternoon congestion.',      'type' => 'recommendation', 'value' => 'Shift'],
            ['title' => 'Queue Backlog',         'description' => 'Pending lab reservations have been sitting in the admin queue for over 24 hours.',                     'type' => 'alert',          'value' => 'Action'],
            ['title' => 'Midterm Spike',         'description' => 'System logs show a 24% week-over-week increase in sit-in sessions due to scheduled exams.',            'type' => 'trend',          'value' => '+24%'],
        ],
        'summary' => 'The system is operating within normal parameters. Review the queue backlog and consider rerouting peak-hour traffic to underutilized morning slots.',
    ];
    AiAuthMiddleware::logUsage($userId, $role, 'admin_insights', $db);
    sendSuccess(200, 'Sandbox insights generated', $sandbox, ['_sandbox' => true]);
    exit;
}

// ── 3. Build context via AiContextBuilder ─────────────────────────────────────
try {
    $contextBuilder = new AiContextBuilder($db);
    $context        = $contextBuilder->forAdmin();

    // Pre-compute week-over-week change for the prompt
    $lastWeek  = $context['sessions_last_week'] ?? 0;
    $thisWeek  = $context['sessions_this_week']  ?? 0;
    $wowChange = $lastWeek > 0
        ? round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1)
        : null;

    $resStats    = $context['reservation_stats_30d'] ?? [];
    $totalRes    = array_sum(array_intersect_key($resStats, array_flip(['approved_count','rejected_count','pending_count','cancelled_count'])));
    $approvalRate = ($totalRes > 0)
        ? round((($resStats['approved_count'] ?? 0) / $totalRes) * 100, 1)
        : null;

    // ── 3b. Compute data fingerprint from key metrics ────────────────────────
    $fingerprint = AiCache::fingerprint([
        'active'   => $context['active_sessions_count'],
        'pending'  => $context['pending_reservations_count'],
        'today'    => $context['sessions_today'],
        'week'     => $thisWeek,
        'lastWeek' => $lastWeek,
        'avgDur'   => $context['avg_session_duration_min'] ?? 0,
        'resStats' => $resStats,
    ]);

    // If bypass_cache was requested, check if data actually changed
    if ($bypassCache) {
        $fresh = AiCache::checkFreshness($db, $cacheKey, $fingerprint);
        if ($fresh) {
            // Data hasn't changed — return cached insights with _data_unchanged flag
            sendSuccess(200, 'Data unchanged since last diagnosis. Returning cached insights.', $fresh, ['_cache' => true, '_data_unchanged' => true]);
            exit;
        }
        // Data has changed — continue to regeneration below
    }

    // ── 4. Prompt — context is already aggregated by AiContextBuilder ────────
    $prompt  = "You are a senior university operations strategist advising laboratory administrators.\n\n";
    $prompt .= "Analyze the LIVE operational data below and produce EXACTLY 4 strategic insight cards.\n";
    $prompt .= "Each insight must involve cross-correlation, trend prediction, or a specific actionable recommendation.\n";
    $prompt .= "DO NOT restate raw counts ('there are 8 pending reservations'). ";
    $prompt .= "An admin already sees those numbers. Analyze patterns and recommend actions.\n";
    $prompt .= "Output ONLY a raw JSON object. No markdown, no code blocks.\n\n";

    $prompt .= "## Live Operational Snapshot\n";
    $prompt .= "- Active sit-ins right now: {$context['active_sessions_count']}\n";
    $prompt .= "- Pending reservations: {$context['pending_reservations_count']}\n";
    $prompt .= "- Sessions today: {$context['sessions_today']}\n";
    $prompt .= "- Sessions this week (Mon–now): {$thisWeek}\n";
    $prompt .= "- Sessions last week (same duration): {$lastWeek}\n";
    if ($wowChange !== null) {
        $sign    = $wowChange >= 0 ? '+' : '';
        $prompt .= "- Week-over-week change: {$sign}{$wowChange}%\n";
    }
    $prompt .= "- Average session duration: " . ($context['avg_session_duration_min'] ?? 0) . " minutes\n\n";

    if (!empty($resStats)) {
        $prompt .= "## Reservation Pipeline (Last 30 Days)\n";
        $prompt .= "- Approved: {$resStats['approved_count']}, Rejected: {$resStats['rejected_count']}, ";
        $prompt .= "Pending: {$resStats['pending_count']}, Cancelled: {$resStats['cancelled_count']}\n";
        if ($approvalRate !== null) {
            $prompt .= "- Approval rate: {$approvalRate}%\n";
        }
        $prompt .= "\n";
    }

    if (!empty($context['lab_utilization'])) {
        $prompt .= "## Lab Utilization (This Week)\n";
        foreach ($context['lab_utilization'] as $l) {
            $prompt .= "- {$l['lab_code']} ({$l['lab_name']}): {$l['session_count']} sessions, {$l['total_minutes']} total minutes\n";
        }
        $prompt .= "\n";
    }

    if (!empty($context['purpose_distribution'])) {
        $prompt .= "## Purpose Distribution (30 Days)\n";
        foreach ($context['purpose_distribution'] as $p) {
            $prompt .= "- {$p['purpose']}: {$p['count']} sessions\n";
        }
        $prompt .= "\n";
    }

    if (!empty($context['hourly_distribution'])) {
        $prompt .= "## Busiest Hours (14 Days)\n";
        foreach ($context['hourly_distribution'] as $h) {
            $hour    = (int)$h['hour'];
            $ampm    = $hour < 12 ? 'AM' : 'PM';
            $display = $hour === 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour);
            $prompt .= "- {$display}:00 {$ampm}: {$h['count']} sessions\n";
        }
        $prompt .= "\n";
    }

    if (!empty($context['top_students_this_month'])) {
        $prompt .= "## Top Students This Month\n";
        foreach ($context['top_students_this_month'] as $s) {
            $prompt .= "- {$s['name']}: {$s['total_minutes']} minutes\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "## Output Format\n";
    $prompt .= "Return a JSON object with exactly two keys:\n";
    $prompt .= "- 'cards': Array of exactly 4 objects. Each must contain:\n";
    $prompt .= "  - 'title': 3-5 words, e.g. 'Capacity Rebalancing Needed'\n";
    $prompt .= "  - 'description': 15-30 words. Cross-correlated insight with a specific action. Must reference actual data relationships.\n";
    $prompt .= "  - 'type': Exactly one of: 'utilization' | 'recommendation' | 'alert' | 'trend'\n";
    $prompt .= "  - 'value': Derived metric, max 10 chars, e.g. '+24% WoW', '3:1 Ratio'\n";
    $prompt .= "- 'summary': 2-3 sentence plain-English executive briefing for the admin. What is the most important thing to act on today?\n\n";
    $prompt .= "GOOD: 'Lab 527 handles 60% of C Programming sessions but only has 30 PCs — route overflow to Lab 525 which is 80% idle during those hours.'\n";
    $prompt .= "BAD: 'There are 8 pending reservations.' or 'Only 1 session today.'\n";

    $rawResponse = callGemini($prompt);

    // ── 5. Handle AI failure / rate limit ────────────────────────────────────
    if ($rawResponse === null) {
        $aiError = getLastGeminiError();
        if ($aiError && $aiError['http_code'] === 429) {
            $fallback = [
                'cards' => [
                    ['title' => 'Laboratory Peak',  'description' => 'Lab utilization peaks at 88% capacity during busy mid-afternoon blocks. Staff coverage should be pre-assigned.', 'type' => 'utilization',    'value' => '88% Peak'],
                    ['title' => 'Shift Traffic',    'description' => 'Promote Wednesday morning slots to balance usage and relieve Friday afternoon congestion.',                      'type' => 'recommendation', 'value' => 'Shift'],
                    ['title' => 'Queue Backlog',    'description' => ($context['pending_reservations_count'] ?? 0) . ' reservations are pending review. Extended backlogs reduce student satisfaction.', 'type' => 'alert', 'value' => 'Action'],
                    ['title' => 'Weekly Activity', 'description' => "This week logged {$thisWeek} sessions" . ($wowChange !== null ? " ({$wowChange}% vs last week)." : '.') . ' Monitor for exam-period spikes.', 'type' => 'trend', 'value' => ($wowChange !== null ? ($wowChange >= 0 ? '+' : '') . $wowChange . '%' : 'Active')],
                ],
                'summary'       => 'System is operational. Review the reservation queue and monitor lab load during peak afternoon hours.',
                'is_fallback'   => true,
                'fallback_reason' => 'Rate limit cooldown active',
            ];
            AiAuthMiddleware::logUsage($userId, $role, 'admin_insights', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving local insights.', $fallback);
            exit;
        }
        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to fetch insights from AI provider.', $isDev ? $aiError : null);
    }

    // ── 6. Parse and merge ────────────────────────────────────────────────────
    $cleanedJson = stripMarkdownFences($rawResponse);
    $aiData      = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($aiData['cards'])) {
        error_log('Gemini Admin Insights JSON Parse Error: ' . $rawResponse);
        sendError(500, 'AI response format was invalid. Please try again.');
    }

    $payload = [
        'cards'   => $aiData['cards'],
        'summary' => $aiData['summary'] ?? null,
        '_generated_at' => date('Y-m-d H:i:s'),
    ];

    // ── 7. Cache (6-hour TTL, shared) ────────────────────────────────────────
    AiCache::set($db, $cacheKey, 'admin_insights', $payload, ttlHours: 6, fingerprint: $fingerprint);
    AiAuthMiddleware::logUsage($userId, $role, 'admin_insights', $db);
    sendSuccess(200, 'AI admin insights retrieved successfully', $payload);

} catch (Exception $e) {
    sendError(500, 'Server error during admin insights generation.', $e);
}