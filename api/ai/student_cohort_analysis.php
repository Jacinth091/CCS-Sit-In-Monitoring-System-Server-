<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/gemini.php';
require_once '../../src/helpers/AiCache.php';
require_once '../../src/middleware/AiAuthMiddleware.php';

$input  = json_decode(file_get_contents("php://input"), true) ?? [];
$auth   = AiAuthMiddleware::guard('student_cohort_analysis', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

// Only administrators can perform student cohort auditing
$currentUser = requireAdmin();

// ── 1. Cache Check (6-hour TTL, shared across admins) ────────────────────────
$cacheKey = 'student_cohort';
$cached   = AiCache::get($db, $cacheKey);
if ($cached) {
    sendSuccess(200, 'Student cohort analysis retrieved successfully', $cached, ['_cache' => true]);
    exit;
}

// ── 2. SQL aggregation layer (runs always, free of AI cost) ──────────────────
try {
    $cohortQuery = "
        SELECT
            s.student_id,
            s.first_name || ' ' || s.last_name            AS full_name,
            s.course,
            s.session                                      AS credits_remaining,
            COUNT(DISTINCT l.id)                           AS sessions_30d,
            COUNT(DISTINCT r.id)                           AS reservations_30d,
            COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END)
                                                           AS cancellations,
            CASE
                WHEN s.session <= 5 AND COUNT(DISTINCT r.id) > 0   THEN 'credit_risk'
                WHEN COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) >= 3
                                                           THEN 'cancellation_risk'
                WHEN COUNT(DISTINCT l.id) = 0 AND COUNT(DISTINCT r.id) > 0  THEN 'no_show_risk'
                WHEN COUNT(DISTINCT l.id) >= 15                     THEN 'heavy_user'
                ELSE 'normal'
            END                                            AS cohort_tag
        FROM students s
        LEFT JOIN sit_in_logs l
               ON l.student_id = s.student_id
              AND l.time_in >= CURRENT_DATE - INTERVAL '30 days'
              AND l.deleted_at IS NULL
        LEFT JOIN reservations r
               ON r.student_id = s.student_id
              AND r.reserved_date >= CURRENT_DATE - INTERVAL '30 days'
              AND r.deleted_at IS NULL
        WHERE s.deleted_at IS NULL AND s.is_active = TRUE
        GROUP BY s.student_id, s.first_name, s.last_name, s.course, s.session
        HAVING CASE
            WHEN s.session <= 5 AND COUNT(DISTINCT r.id) > 0   THEN TRUE
            WHEN COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) >= 3 THEN TRUE
            WHEN COUNT(DISTINCT l.id) = 0 AND COUNT(DISTINCT r.id) > 0  THEN TRUE
            WHEN COUNT(DISTINCT l.id) >= 15                     THEN TRUE
            ELSE FALSE
        END
        ORDER BY
            CASE
                WHEN s.session <= 5 AND COUNT(DISTINCT r.id) > 0 THEN 1
                WHEN COUNT(DISTINCT l.id) = 0 AND COUNT(DISTINCT r.id) > 0 THEN 2
                WHEN COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) >= 3 THEN 3
                ELSE 4
            END
    ";
    
    $stmt = $db->query($cohortQuery);
    $cohorts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate cohort counts
    $counts = [
        'credit_risk'       => 0,
        'no_show_risk'      => 0,
        'cancellation_risk' => 0,
        'heavy_user'        => 0
    ];
    foreach ($cohorts as $row) {
        $tag = $row['cohort_tag'];
        if (isset($counts[$tag])) {
            $counts[$tag]++;
        }
    }

    // Get total active students count
    $totalActiveStmt = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL AND is_active = TRUE");
    $totalActive = (int)$totalActiveStmt->fetchColumn();

    // Slice top 5 notable flagged students for the lightweight AI prompt context
    $notable = array_slice($cohorts, 0, 5);

} catch (Exception $e) {
    sendError(500, 'Database aggregation failure during cohort extraction.', $e);
}

// ── 3. Sandbox Mode Check ────────────────────────────────────────────────────
if (!AI_ENABLED) {
    $sandbox = buildCohortFallback($cohorts, $counts, $totalActive);
    AiAuthMiddleware::logUsage($userId, $role, 'student_cohort_analysis', $db);
    sendSuccess(200, 'Sandbox cohort analysis generated', $sandbox, ['_sandbox' => true]);
    exit;
}

// ── 4. Live AI Inference (narrates pre-computed SQL numbers) ──────────────────
try {
    $prompt = "You are an admin assistant for a university computer lab.\n";
    $prompt .= "You are given a pre-classified list of student behavioral cohorts. Write a concise operational briefing for the lab administrator.\n";
    $prompt .= "Be direct and specific — use actual names and numbers from the data. Do NOT fabricate any figures.\n";
    $prompt .= "Keep the total response under 120 words.\n";
    $prompt .= "Your output must be EXACTLY a raw JSON object. No markdown, no filler.\n\n";

    $prompt .= "## Behavioral Cohort Data Context\n";
    $prompt .= "Counts:\n";
    $prompt .= "- credit_risk: " . $counts['credit_risk'] . " students\n";
    $prompt .= "- no_show_risk: " . $counts['no_show_risk'] . " students\n";
    $prompt .= "- cancellation_risk: " . $counts['cancellation_risk'] . " students\n";
    $prompt .= "- heavy_user: " . $counts['heavy_user'] . " students\n\n";

    $prompt .= "## Total Active Students\n";
    $prompt .= "- Total: " . $totalActive . "\n\n";

    $prompt .= "## Top Flagged Students (most notable)\n";
    foreach ($notable as $ns) {
        $prompt .= "- Name: " . $ns['full_name'] . ", Cohort: " . $ns['cohort_tag'] . ", Credits remaining: " . $ns['credits_remaining'] . ", Sessions: " . $ns['sessions_30d'] . "\n";
    }

    $prompt .= "\n## Required Output JSON format:\n";
    $prompt .= "{\n";
    $prompt .= "  \"summary\": \"Plain-English narrative paragraph, max 120 words.\",\n";
    $prompt .= "  \"urgent_cohort\": \"credit_risk | no_show_risk | cancellation_risk | heavy_user\",\n";
    $prompt .= "  \"action\": \"Single recommended admin action string.\"\n";
    $prompt .= "}\n";

    $rawResponse = callGemini($prompt);

    // ── 5. AI Failure or Rate Limit ──────────────────────────────────────────
    if ($rawResponse === null) {
        $aiError = getLastGeminiError();
        $fallback = buildCohortFallback($cohorts, $counts, $totalActive);
        if ($aiError && $aiError['http_code'] === 429) {
            $fallback['is_fallback']     = true;
            $fallback['fallback_reason'] = 'Rate limit cooldown active';
            AiAuthMiddleware::logUsage($userId, $role, 'student_cohort_analysis', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving pre-aggregated cohorts.', $fallback);
            exit;
        }
        
        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to fetch cohort analysis from AI provider.', $isDev ? $aiError : null);
    }

    // ── 6. Parse and Cache successful results ─────────────────────────────────
    $cleanedJson = stripMarkdownFences($rawResponse);
    $aiData      = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($aiData['summary'])) {
        error_log('Gemini Cohort Analysis JSON Parse Error: ' . $rawResponse);
        sendError(500, 'AI response format was invalid.');
    }

    $payload = [
        'cohorts'       => $cohorts,
        'counts'        => $counts,
        'summary'       => $aiData['summary'],
        'urgent_cohort' => $aiData['urgent_cohort'] ?? 'no_show_risk',
        'action'        => $aiData['action']        ?? 'Monitor active reservations.',
    ];

    AiCache::set($db, $cacheKey, 'student_cohort', $payload, ttlHours: 6);
    AiAuthMiddleware::logUsage($userId, $role, 'student_cohort_analysis', $db);
    sendSuccess(200, 'AI student cohort analysis retrieved successfully', $payload);

} catch (Exception $e) {
    sendError(500, 'Server error during cohort analysis.', $e);
}

// ── Helper: build deterministic fallback using real database facts ───────────
function buildCohortFallback(array $cohorts, array $counts, int $totalActive): array {
    $urgent = 'no_show_risk';
    if ($counts['credit_risk'] > $counts['no_show_risk']) {
        $urgent = 'credit_risk';
    }

    $action = "Monitor upcoming reservations and clear unattended slots.";
    if ($urgent === 'credit_risk') {
        $action = "Prompt low-credit active bookers to request top-ups.";
    } elseif ($urgent === 'no_show_risk') {
        $action = "Clear active reservation slots for students who have zero logged sessions in 30 days.";
    }

    $notableNames = [];
    foreach (array_slice($cohorts, 0, 2) as $c) {
        $notableNames[] = $c['full_name'];
    }
    $namesStr = !empty($notableNames) ? implode(" and ", $notableNames) : "students";

    return [
        'cohorts'       => $cohorts,
        'counts'        => $counts,
        'summary'       => "Total active cohort audit maps " . count($cohorts) . " flagged students out of {$totalActive} total active accounts. " .
                           "Risk lists identify {$counts['no_show_risk']} no-shows and {$counts['credit_risk']} low-credit accounts. " .
                           "Specifically, notable cases like {$namesStr} warrant observation due to mismatch in session activity vs reservation holdings.",
        'urgent_cohort' => $urgent,
        'action'        => $action
    ];
}
