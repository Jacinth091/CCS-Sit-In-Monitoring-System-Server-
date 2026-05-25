<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/AiContextBuilder.php';
require_once '../../src/helpers/gemini.php';
require_once '../../src/helpers/groq.php';
require_once '../../src/helpers/AiCache.php';
require_once '../../src/middleware/AiAuthMiddleware.php';

$input   = json_decode(file_get_contents("php://input"), true) ?? [];
$records = $input['records'] ?? [];

$auth   = AiAuthMiddleware::guard('report_summary', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

$currentUser = requireAdmin();

if (!is_array($records) || empty($records)) {
    sendError(400, 'Invalid request. A non-empty records array is required.');
}

$recordCount = count($records);

// ── 1. Cache check — keyed on a hash of the record IDs so different report
//       filters produce different cache entries. TTL: 2 hours. ───────────────
$recordIds = array_column($records, 'id');
sort($recordIds);
$cacheKey  = 'report_summary:v3:' . md5(implode(',', $recordIds));
$cached    = AiCache::get($db, $cacheKey);
if ($cached) {
    sendSuccess(200, 'Report summary retrieved', $cached, ['_cache' => true]);
    exit;
}

// ── 2. Sandbox fallback ───────────────────────────────────────────────────────
if (!AI_ENABLED) {
    $sandbox = buildReportFallback($recordCount);
    AiAuthMiddleware::logUsage($userId, $role, 'report_summary', $db);
    sendSuccess(200, 'Sandbox report summary generated', $sandbox, ['_sandbox' => true]);
    exit;
}

// ── 3. SQL-side pre-aggregation — compute metrics from the submitted record IDs
//       rather than sending raw rows to the AI. ───────────────────────────────
try {
    // Pluck up to 500 IDs to keep the IN clause safe
    $safeIds = array_slice(array_map('strval', $recordIds), 0, 500);

    if (!empty($safeIds)) {
        $placeholders = implode(',', array_fill(0, count($safeIds), '?'));

        // Purpose breakdown
        $stmtPurpose = $db->prepare("
            SELECT purpose, COUNT(*) AS cnt
            FROM sit_in_logs
            WHERE id IN ({$placeholders}) AND deleted_at IS NULL
            GROUP BY purpose
            ORDER BY cnt DESC
            LIMIT 5
        ");
        $stmtPurpose->execute($safeIds);
        $purposeDist = $stmtPurpose->fetchAll(PDO::FETCH_ASSOC);

        // Lab breakdown
        $stmtLab = $db->prepare("
            SELECT l.name AS lab_name, l.lab_code, COUNT(*) AS cnt
            FROM sit_in_logs s
            JOIN laboratories l ON l.id = s.lab_id
            WHERE s.id IN ({$placeholders}) AND s.deleted_at IS NULL
            GROUP BY l.id, l.name, l.lab_code
            ORDER BY cnt DESC
            LIMIT 5
        ");
        $stmtLab->execute($safeIds);
        $labDist = $stmtLab->fetchAll(PDO::FETCH_ASSOC);

        // Average session duration + date range
        $stmtMeta = $db->prepare("
            SELECT
                ROUND(AVG(EXTRACT(EPOCH FROM (time_out - time_in)) / 60), 1) AS avg_duration_min,
                MIN(time_in::date) AS date_from,
                MAX(time_in::date) AS date_to,
                COUNT(CASE WHEN status = 'completed' THEN 1 END)             AS completed_count,
                COUNT(CASE WHEN status = 'ongoing'   THEN 1 END)             AS ongoing_count
            FROM sit_in_logs
            WHERE id IN ({$placeholders})
              AND deleted_at IS NULL
              AND time_out IS NOT NULL
        ");
        $stmtMeta->execute($safeIds);
        $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);

        // Top course breakdown
        $stmtCourse = $db->prepare("
            SELECT s.course, COUNT(*) AS cnt
            FROM sit_in_logs sl
            JOIN students s ON s.student_id = sl.student_id
            WHERE sl.id IN ({$placeholders}) AND sl.deleted_at IS NULL
            GROUP BY s.course
            ORDER BY cnt DESC
            LIMIT 3
        ");
        $stmtCourse->execute($safeIds);
        $courseDist = $stmtCourse->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $purposeDist = $labDist = $courseDist = [];
        $meta = [];
    }

    // ── 4. Prompt — AI receives aggregated metrics, not raw rows ─────────────
    $prompt  = "Analyze these pre-aggregated report metrics for a university computer laboratory and generate a concise executive summary.\n";
    $prompt .= "Output ONLY a raw JSON object. No markdown, no code blocks.\n\n";

    $prompt .= "## Report Metadata\n";
    $prompt .= "- Total records in report: {$recordCount}\n";
    $prompt .= "- Completed sessions: " . ($meta['completed_count'] ?? 'N/A') . "\n";
    $prompt .= "- Ongoing sessions: "   . ($meta['ongoing_count']   ?? 'N/A') . "\n";
    $prompt .= "- Average session duration: " . ($meta['avg_duration_min'] ?? 'N/A') . " minutes\n";
    $prompt .= "- Date range covered: " . ($meta['date_from'] ?? 'N/A') . " to " . ($meta['date_to'] ?? 'N/A') . "\n\n";

    if (!empty($purposeDist)) {
        $prompt .= "## Top Session Purposes\n";
        foreach ($purposeDist as $p) {
            $prompt .= "- {$p['purpose']}: {$p['cnt']} sessions\n";
        }
        $prompt .= "\n";
    }

    if (!empty($labDist)) {
        $prompt .= "## Top Labs by Session Volume\n";
        foreach ($labDist as $l) {
            $prompt .= "- {$l['lab_name']} ({$l['lab_code']}): {$l['cnt']} sessions\n";
        }
        $prompt .= "\n";
    }

    if (!empty($courseDist)) {
        $prompt .= "## Top Courses\n";
        foreach ($courseDist as $c) {
            $prompt .= "- {$c['course']}: {$c['cnt']} sessions\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "## Output Format\n";
    $prompt .= "Return a JSON object with exactly these keys:\n";
    $prompt .= "- 'headline': 6 words max. High-level report title, e.g. 'Lab 5 Leads Weekly Utilization'\n";
    $prompt .= "- 'summary': 1 paragraph, max 50 words. Professional narrative outlining patterns and key takeaways from the aggregated data.\n";
    $prompt .= "- 'metrics': Array of exactly 3 objects. Each must contain:\n";
    $prompt .= "  - 'label': max 3 words, e.g. 'Peak Hour', 'Busy Lab'\n";
    $prompt .= "  - 'value': max 8 chars, e.g. 'Lab 5', 'Coding', '92 min'\n";
    $prompt .= "  - 'desc': max 5 words, e.g. 'highest traffic laboratory'\n";

    // Use Groq as primary for this endpoint as per user preference/reliability
    $systemPrompt = "You are a professional administrative reporting assistant. Respond only with valid JSON.";
    $messages = [['role' => 'user', 'content' => $prompt]];
    $rawResponse = callGroq($systemPrompt, $messages, SUMMARY_MAX_TOKENS);

    // ── 5. Handle AI failure / rate limit ────────────────────────────────────
    if ($rawResponse === null) {
        $aiError  = getLastGroqError();
        $fallback = buildReportFallback($recordCount, $meta, $labDist, $purposeDist);
        if ($aiError && $aiError['http_code'] === 429) {
            $fallback['is_fallback']     = true;
            $fallback['fallback_reason'] = 'Rate limit cooldown active';
            AiAuthMiddleware::logUsage($userId, $role, 'report_summary', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving local report summary.', $fallback);
            exit;
        }
        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to summarize report from AI provider.', $isDev ? $aiError : null);
    }

    // ── 6. Parse + merge SQL aggregates + AI narrative ────────────────────────
    $cleanedJson = stripMarkdownFences($rawResponse);
    $aiData      = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($aiData['headline'])) {
        error_log('Groq Report Summary Parse Error: ' . $rawResponse);
        sendError(500, 'AI response format was invalid. Please try again.');
    }

    $topLabName   = $labDist[0]['lab_code'] ?? ($labDist[0]['lab_name'] ?? '—');
    $topCourse    = $courseDist[0]['course'] ?? '—';
    $avgDuration  = isset($meta['avg_duration_min']) ? ((int) round((float) $meta['avg_duration_min'])) . ' min' : '—';

    $payload = [
        'headline'     => $aiData['headline'],
        'summary'      => $aiData['summary'],
        // Force key metrics from real aggregates to avoid AI drift
        'metrics'      => [
            ['label' => 'Busy Lab',    'value' => (string) $topLabName,  'desc' => 'highest traffic'],
            ['label' => 'Peak Session','value' => (string) $avgDuration, 'desc' => 'avg duration'],
            ['label' => 'Top Course',  'value' => (string) $topCourse,   'desc' => 'most sessions'],
        ],
        // Raw SQL aggregates included so frontend can render tables independently
        'aggregates'   => [
            'purpose_distribution' => $purposeDist,
            'lab_distribution'     => $labDist,
            'course_distribution'  => $courseDist,
            'meta'                 => $meta,
        ],
    ];

    // ── 7. Cache (2-hour TTL, keyed on record hash) ───────────────────────────
    AiCache::set($db, $cacheKey, 'report_summary', $payload, ttlHours: 2, modelUsed: GROQ_CHAT_MODEL);
    AiAuthMiddleware::logUsage($userId, $role, 'report_summary', $db);
    sendSuccess(200, 'AI report summary generated successfully', $payload);

} catch (Exception $e) {
    sendError(500, 'Server error during report summary extraction.', $e);
}

// ── Helper: deterministic fallback using real SQL data where available ────────
function buildReportFallback(int $recordCount, array $meta = [], array $labDist = [], array $purposeDist = []): array {
    $topLab     = $labDist[0]['lab_name']  ?? 'Lab 5';
    $topPurpose = $purposeDist[0]['purpose'] ?? 'Coding';
    $avgDur     = $meta['avg_duration_min'] ?? '~90';

    return [
        'headline' => 'Laboratory Usage Report Compiled',
        'summary'  => "Analyzed {$recordCount} records. Sessions are concentrated in {$topPurpose} activities. {$topLab} leads in traffic volume with an average session duration of approximately {$avgDur} minutes.",
        'metrics'  => [
            ['label' => 'Total Volume',   'value' => (string)$recordCount,  'desc'  => 'logs analyzed'],
            ['label' => 'Top Purpose',    'value' => $topPurpose,            'desc'  => 'most frequent reason'],
            ['label' => 'Peak Lab',       'value' => $topLab,                'desc'  => 'highest traffic volume'],
        ],
    ];
}