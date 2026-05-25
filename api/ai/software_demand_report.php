<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/gemini.php';
require_once '../../src/helpers/AiCache.php';
require_once '../../src/middleware/AiAuthMiddleware.php';

$input  = json_decode(file_get_contents("php://input"), true) ?? [];
$auth   = AiAuthMiddleware::guard('software_demand_report', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

// Only administrators can perform software demand reporting
$currentUser = requireAdmin();

// ── 1. Cache Check (24-hour TTL, shared across admins) ───────────────────────
$cacheKey = 'software_demand';
$cached   = AiCache::get($db, $cacheKey);
if ($cached) {
    sendSuccess(200, 'Software demand report retrieved successfully', $cached, ['_cache' => true]);
    exit;
}

// ── 2. SQL aggregation layer (runs always, free of AI cost) ──────────────────
try {
    $softwareQuery = "
        SELECT
            sr.software_name,
            COUNT(*)                                              AS request_count,
            COUNT(CASE WHEN sr.status = 'pending'  THEN 1 END)   AS pending_count,
            COUNT(CASE WHEN sr.status = 'reviewed' THEN 1 END)   AS reviewed_count,
            COALESCE(STRING_AGG(DISTINCT s.course, ', '), 'N/A') AS requesting_courses,
            MAX(sr.created_at)                                    AS latest_request,
            COALESCE(BOOL_OR(ls.software_id IS NOT NULL), FALSE)  AS is_installed
        FROM student_software_requests sr
        JOIN students s           ON s.student_id = sr.student_id
        LEFT JOIN software sw     ON LOWER(sw.name) = LOWER(sr.software_name) AND sw.deleted_at IS NULL
        LEFT JOIN lab_software ls ON ls.software_id = sw.id
        GROUP BY sr.software_name
        ORDER BY
            is_installed ASC,
            request_count DESC
        LIMIT 20
    ";
    
    $stmt = $db->query($softwareQuery);
    $softwareList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter notable aggregations for lightweight AI prompt context
    $topUninstalled = [];
    $stalePending = [];
    $totalUnique = count($softwareList);

    foreach ($softwareList as $sw) {
        // Top 5 uninstalled
        if (!$sw['is_installed'] && count($topUninstalled) < 5) {
            $topUninstalled[] = [
                'name'          => $sw['software_name'],
                'request_count' => $sw['request_count'],
                'courses'       => $sw['requesting_courses']
            ];
        }

        // Stale pending: pending > 0 and latest request older than 14 days
        $isStale = false;
        if ($sw['pending_count'] > 0 && !empty($sw['latest_request'])) {
            $daysSince = (time() - strtotime($sw['latest_request'])) / 86400;
            if ($daysSince > 14) {
                $isStale = true;
            }
        }
        if ($isStale) {
            $stalePending[] = [
                'name'          => $sw['software_name'],
                'pending_count' => $sw['pending_count'],
                'days_stale'    => (int)floor((time() - strtotime($sw['latest_request'])) / 86400)
            ];
        }
    }

} catch (Exception $e) {
    sendError(500, 'Database aggregation failure during software demand extraction.', $e);
}

// ── 3. Sandbox Mode Check ────────────────────────────────────────────────────
if (!AI_ENABLED) {
    $sandbox = buildSoftwareFallback($softwareList, $topUninstalled, $stalePending, $totalUnique);
    AiAuthMiddleware::logUsage($userId, $role, 'software_demand_report', $db);
    sendSuccess(200, 'Sandbox software demand report generated', $sandbox, ['_sandbox' => true]);
    exit;
}

// ── 4. Live AI Inference (narrates pre-computed SQL numbers) ──────────────────
try {
    $prompt = "You are an IT resource planner for a university computer lab.\n";
    $prompt .= "You are given a ranked list of student software requests with installation status. Write a brief procurement briefing for the lab administrator.\n";
    $prompt .= "Be specific about software names and request counts. Do NOT fabricate figures.\n";
    $prompt .= "Keep total response under 100 words.\n";
    $prompt .= "Your output must be EXACTLY a raw JSON object. No markdown, no filler.\n\n";

    $prompt .= "## Aggregated Request Context\n";
    $prompt .= "Total unique software requested: " . $totalUnique . "\n\n";

    $prompt .= "## Top Uninstalled Requests\n";
    if (empty($topUninstalled)) {
        $prompt .= "- No uninstalled requests found.\n";
    } else {
        foreach ($topUninstalled as $tu) {
            $prompt .= "- " . $tu['name'] . ": " . $tu['request_count'] . " requests from courses (" . $tu['courses'] . ")\n";
        }
    }
    $prompt .= "\n";

    $prompt .= "## Stale Pending Requests (pending & older than 14 days)\n";
    if (empty($stalePending)) {
        $prompt .= "- No stale pending requests.\n";
    } else {
        foreach ($stalePending as $sp) {
            $prompt .= "- " . $sp['name'] . ": " . $sp['pending_count'] . " pending requests (latest request " . $sp['days_stale'] . " days ago)\n";
        }
    }

    $prompt .= "\n## Required Output JSON format:\n";
    $prompt .= "{\n";
    $prompt .= "  \"summary\": \"Plain-English narrative paragraph, max 100 words.\",\n";
    $prompt .= "  \"top_priority\": \"Name of the highest-demand uninstalled software.\",\n";
    $prompt .= "  \"stale_flag\": \"Description of stale pending requests, or null if none.\"\n";
    $prompt .= "}\n";

    $rawResponse = callGemini($prompt);

    // ── 5. AI Failure or Rate Limit ──────────────────────────────────────────
    if ($rawResponse === null) {
        $aiError = getLastGeminiError();
        $fallback = buildSoftwareFallback($softwareList, $topUninstalled, $stalePending, $totalUnique);
        if ($aiError && $aiError['http_code'] === 429) {
            $fallback['is_fallback']     = true;
            $fallback['fallback_reason'] = 'Rate limit cooldown active';
            AiAuthMiddleware::logUsage($userId, $role, 'software_demand_report', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving pre-aggregated software list.', $fallback);
            exit;
        }

        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to fetch software demand report from AI provider.', $isDev ? $aiError : null);
    }

    // ── 6. Parse and Cache successful results ─────────────────────────────────
    $cleanedJson = stripMarkdownFences($rawResponse);
    $aiData      = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($aiData['summary'])) {
        error_log('Gemini Software Demand JSON Parse Error: ' . $rawResponse);
        sendError(500, 'AI response format was invalid.');
    }

    $payload = [
        'software_list' => $softwareList,
        'summary'       => $aiData['summary'],
        'top_priority'  => $aiData['top_priority'] ?? (!empty($topUninstalled) ? $topUninstalled[0]['name'] : 'None'),
        'stale_flag'    => $aiData['stale_flag']    ?? null,
    ];

    AiCache::set($db, $cacheKey, 'software_demand', $payload, ttlHours: 24);
    AiAuthMiddleware::logUsage($userId, $role, 'software_demand_report', $db);
    sendSuccess(200, 'AI software demand report retrieved successfully', $payload);

} catch (Exception $e) {
    sendError(500, 'Server error during software demand extraction.', $e);
}

// ── Helper: build deterministic fallback using real database facts ───────────
function buildSoftwareFallback(array $softwareList, array $topUninstalled, array $stalePending, int $totalUnique): array {
    $topPriority = !empty($topUninstalled) ? $topUninstalled[0]['name'] : 'None';
    $topCount    = !empty($topUninstalled) ? $topUninstalled[0]['request_count'] : 0;
    $topCourses  = !empty($topUninstalled) ? $topUninstalled[0]['courses'] : 'N/A';

    $staleStrList = [];
    foreach (array_slice($stalePending, 0, 2) as $sp) {
        $staleStrList[] = "{$sp['name']} ({$sp['days_stale']} days pending)";
    }
    $staleFlag = !empty($staleStrList) ? implode(", ", $staleStrList) : null;

    $summary = "Students have requested {$totalUnique} distinct software tools that are not fully deployed across all computer laboratories. ";
    if ($topPriority !== 'None') {
        $summary .= "{$topPriority} leads procurement demand with {$topCount} total requests, particularly from {$topCourses} classes. ";
    }
    if (!empty($stalePending)) {
        $summary .= "There are " . count($stalePending) . " software requests that have been pending action for over 14 days. ";
    }
    $summary .= "Recommend coordinating with lab administrators to prioritize {$topPriority} installation during upcoming maintenance slots.";

    return [
        'software_list' => $softwareList,
        'summary'       => $summary,
        'top_priority'  => $topPriority,
        'stale_flag'    => $staleFlag,
    ];
}
