<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai_limits.php';

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
        'limit' => GLOBAL_BUDGET_CHAT_DAILY,
    ],
    'analysis' => [
        'used' => (int)$todayBudget['analysis_calls'],
        'limit' => GLOBAL_BUDGET_ANALYSIS_DAILY,
    ],
    'summary' => [
        'used' => (int)$todayBudget['summary_calls'],
        'limit' => GLOBAL_BUDGET_SUMMARY_DAILY,
    ]
];

// 3. Get past 7 days of global budget calls for analytics chart
$stmt2 = $db->query("SELECT * FROM ai_global_budget ORDER BY budget_date DESC LIMIT 7");
$history = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// 4. Get recent abuse logs
$stmt3 = $db->query("SELECT * FROM ai_abuse_log ORDER BY attempted_at DESC LIMIT 20");
$abuseLogs = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// 5. Calculate active cooldowns / blocked count
$stmt4 = $db->query("SELECT COUNT(DISTINCT identifier) FROM ai_abuse_log WHERE attempted_at >= NOW() - INTERVAL '15 minutes'");
$activeBlocksCount = (int)$stmt4->fetchColumn();

$response = [
    'provider_limits' => $providerLimits,
    'global_limits'   => $globalLimits,
    'history'         => array_reverse($history),
    'abuse_logs'      => $abuseLogs,
    'active_blocks'   => $activeBlocksCount
];

sendSuccess(200, 'Admin AI dashboard statistics retrieved', $response);
