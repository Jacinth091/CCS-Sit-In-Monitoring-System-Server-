<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai_limits.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/gemini.php';
require_once '../../src/helpers/AiCache.php';

// Auth - validate JWT
$currentUser = requireAuth();
if ($currentUser->role !== 'admin') {
    sendError(403, 'Unauthorized. Admin access required.');
}

// 1. Get cached provider rate limits
$cacheFile = __DIR__ . '/../../config/ai_rate_limits_cache.json';
$providerLimits = [];
if (file_exists($cacheFile)) {
    $providerLimits = json_decode(file_get_contents($cacheFile), true) ?? [];
}

// 2. Get today's usage vs global budgets
$stmt = $db->query("SELECT * FROM ai_global_budget WHERE budget_date = CURRENT_DATE");
$todayBudget = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$todayBudget) {
    $todayBudget = [
        'chat_calls' => 0,
        'analysis_calls' => 0,
        'summary_calls' => 0
    ];
}

// Calculate local database tracking values for Gemini rate limits
$geminiLimit = (int)GLOBAL_BUDGET_ANALYSIS_DAILY + (int)GLOBAL_BUDGET_SUMMARY_DAILY;
$geminiUsed = (int)$todayBudget['analysis_calls'] + (int)$todayBudget['summary_calls'];
$secondsUntilMidnight = strtotime('tomorrow') - time();
$hours = floor($secondsUntilMidnight / 3600);
$minutes = floor(($secondsUntilMidnight % 3600) / 60);

$cachedGemini = $providerLimits['gemini'] ?? [];
$providerLimits['gemini'] = [
    'model' => 'gemini-2.5-flash',
    'updated_at' => $cachedGemini['updated_at'] ?? date('Y-m-d H:i:s'),
    'status' => $cachedGemini['status'] ?? 'operational',
    'http_code' => $cachedGemini['http_code'] ?? 200,
    'retry_until' => $cachedGemini['retry_until'] ?? null,
    'limit_requests' => (string)$geminiLimit,
    'limit_tokens' => '4,000,000 (TPM)',
    'remaining_requests' => (string)max(0, $geminiLimit - $geminiUsed),
    'remaining_tokens' => '4,000,000',
    'reset_requests' => "{$hours}h {$minutes}m",
    'reset_tokens' => 'N/A',
    'retry_after' => $cachedGemini['retry_after'] ?? null,
    'is_local' => true
];

$globalLimits = [
    'chat' => [
        'used' => (int)$todayBudget['chat_calls'],
        'limit' => (int)GLOBAL_BUDGET_CHAT_DAILY,
    ],
    'analysis' => [
        'used' => (int)$todayBudget['analysis_calls'],
        'limit' => (int)GLOBAL_BUDGET_ANALYSIS_DAILY,
    ],
    'summary' => [
        'used' => (int)$todayBudget['summary_calls'],
        'limit' => (int)GLOBAL_BUDGET_SUMMARY_DAILY,
    ]
];

// Calculate budget projections
$elapsedFraction = (time() - strtotime('today')) / 86400;
$elapsedFraction = max(0.05, $elapsedFraction);

$projectedChat = round($todayBudget['chat_calls'] / $elapsedFraction, 1);
$projectedAnalysis = round($todayBudget['analysis_calls'] / $elapsedFraction, 1);
$projectedSummary = round($todayBudget['summary_calls'] / $elapsedFraction, 1);

$chatWillExceed = ($projectedChat > GLOBAL_BUDGET_CHAT_DAILY);
$analysisWillExceed = ($projectedAnalysis > GLOBAL_BUDGET_ANALYSIS_DAILY);
$summaryWillExceed = ($projectedSummary > GLOBAL_BUDGET_SUMMARY_DAILY);

$budgetProjections = [
    'chat' => [
        'projected' => $projectedChat,
        'will_exceed' => $chatWillExceed
    ],
    'analysis' => [
        'projected' => $projectedAnalysis,
        'will_exceed' => $analysisWillExceed
    ],
    'summary' => [
        'projected' => $projectedSummary,
        'will_exceed' => $summaryWillExceed
    ]
];

// Calculate provider health scores
$geminiStatus = $providerLimits['gemini']['status'] ?? 'operational';
$healthScore = 100;
if ($geminiStatus === 'rate_limited') {
    $healthScore = 30;
} elseif ($geminiStatus === 'error' || ($providerLimits['gemini']['http_code'] ?? 200) >= 500) {
    $healthScore = 0;
}
$providerLimits['gemini']['health_score'] = $healthScore;

// 3. Get past 7 days of global budget calls for analytics chart
$stmt2 = $db->query("SELECT * FROM ai_global_budget ORDER BY budget_date DESC LIMIT 7");
$history = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// 4. Get recent abuse logs
$stmt3 = $db->query("SELECT * FROM ai_abuse_log ORDER BY attempted_at DESC LIMIT 20");
$abuseLogs = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// 5. Calculate active cooldowns / blocked count
$stmt4 = $db->query("SELECT COUNT(DISTINCT identifier) FROM ai_abuse_log WHERE attempted_at >= NOW() - INTERVAL '15 minutes'");
$activeBlocksCount = (int)$stmt4->fetchColumn();

// 6. Gather additional SQL telemetry facts
$stmtHourly = $db->query("
    SELECT EXTRACT(HOUR FROM time_in) AS hour, COUNT(*) AS count
    FROM sit_in_logs
    WHERE time_in >= NOW() - INTERVAL '14 days' AND deleted_at IS NULL
    GROUP BY hour
    ORDER BY count DESC
    LIMIT 10
");
$hourlyDistribution = $stmtHourly->fetchAll(PDO::FETCH_ASSOC);

$stmtTop = $db->query("
    SELECT s.student_id, s.first_name || ' ' || s.last_name AS name, COUNT(*) AS sessions, SUM(EXTRACT(EPOCH FROM (l.time_out - l.time_in))/60) AS total_minutes
    FROM sit_in_logs l
    JOIN students s ON s.student_id = l.student_id
    WHERE l.time_in >= NOW() - INTERVAL '30 days' AND l.deleted_at IS NULL AND l.time_out IS NOT NULL
    GROUP BY s.student_id, s.first_name, s.last_name
    ORDER BY total_minutes DESC
    LIMIT 5
");
$topUsers = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

// 7. Evaluate conditional trigger for AI operational security audit
$shouldAnalyze = ($chatWillExceed || $analysisWillExceed || $summaryWillExceed || $activeBlocksCount > 5 || $healthScore < 60);
$telemetryAudit = null;

if ($shouldAnalyze) {
    // Try to retrieve from 15-minute telemetry cache
    $cacheKey = 'telemetry_audit';
    $telemetryAudit = AiCache::get($db, $cacheKey);

    if (!$telemetryAudit) {
        if (AI_ENABLED) {
            try {
                $prompt = "You are a proactive system security and database diagnostic AI analyst.\n";
                $prompt .= "You have received stress telemetry indicating high usage projections, active IP blockages, or degraded provider health.\n";
                $prompt .= "Write a concise, 1-paragraph operational briefing for the laboratory administrator explaining this stress status and suggesting 1 concrete intervention.\n";
                $prompt .= "Be direct. Do NOT fabricate any figures. Output ONLY a raw JSON object containing 'summary' and 'urgent_action' keys.\n\n";

                $prompt .= "## System Telemetry Metrics\n";
                $prompt .= "- Active blocks (15 mins): " . $activeBlocksCount . "\n";
                $prompt .= "- Provider health score: " . $healthScore . " / 100\n";
                $prompt .= "- Chat projected today: " . $projectedChat . " (Limit: " . GLOBAL_BUDGET_CHAT_DAILY . ")\n";
                $prompt .= "- Analysis projected today: " . $projectedAnalysis . " (Limit: " . GLOBAL_BUDGET_ANALYSIS_DAILY . ")\n";
                $prompt .= "- Summary projected today: " . $projectedSummary . " (Limit: " . GLOBAL_BUDGET_SUMMARY_DAILY . ")\n\n";

                $prompt .= "## Output Format:\n";
                $prompt .= "{\n";
                $prompt .= "  \"summary\": \"Brief analytical review of the system load and threat profile. Max 80 words.\",\n";
                $prompt .= "  \"urgent_action\": \"Single recommended administrator action.\"\n";
                $prompt .= "}\n";

                $rawResponse = callGemini($prompt);
                if ($rawResponse !== null) {
                    $cleaned = stripMarkdownFences($rawResponse);
                    $parsed = json_decode($cleaned, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($parsed['summary'])) {
                        $telemetryAudit = [
                            'summary' => $parsed['summary'],
                            'urgent_action' => $parsed['urgent_action'] ?? 'Monitor active security logs.',
                            'triggered' => true
                        ];
                        // Cache for 15 minutes (0.25 hours)
                        AiCache::set($db, $cacheKey, 'telemetry_audit', $telemetryAudit, 0.25);
                    }
                }
            } catch (Exception $ex) {
                // Keep resilient
            }
        }

        // Fallback telemetry audit if AI is disabled or fails
        if (!$telemetryAudit) {
            $telemetryAudit = [
                'summary' => "AI rate projections or security blocks have exceeded safe thresholds. Active Blocks: {$activeBlocksCount}. Chat Projected: {$projectedChat}/day. Provider Health: {$healthScore}%.",
                'urgent_action' => "Temporarily raise daily token budgets or check active abuse logs for offending IP ranges.",
                'triggered' => true
            ];
        }
    }
} else {
    $telemetryAudit = [
        'summary' => "All operational rate budgets, server security logs, and AI provider thresholds are operating within nominal parameters. No immediate intervention is required.",
        'urgent_action' => "None needed. System healthy.",
        'triggered' => false
    ];
}

$response = [
    'provider_limits'     => $providerLimits,
    'global_limits'       => $globalLimits,
    'budget_projections'  => $budgetProjections,
    'history'             => array_reverse($history),
    'abuse_logs'          => $abuseLogs,
    'active_blocks'       => $activeBlocksCount,
    'hourly_distribution' => $hourlyDistribution,
    'top_users'           => $topUsers,
    'telemetry_audit'     => $telemetryAudit
];

sendSuccess(200, 'Admin AI dashboard statistics retrieved', $response);
