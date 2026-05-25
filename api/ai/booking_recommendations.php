<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/gemini.php';
require_once '../../src/helpers/AiCache.php';
require_once '../../src/middleware/AiAuthMiddleware.php';

$input  = json_decode(file_get_contents("php://input"), true) ?? [];
$auth   = AiAuthMiddleware::guard('booking_recommendations', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

$currentUser = requireStudent();
$studentId   = $currentUser->student_id;

// ── 1. Cache check (per student, 4-hour TTL) ─────────────────────────────────
$cacheKey = 'booking_rec:' . $studentId;
$cached   = AiCache::get($db, $cacheKey);
if ($cached) {
    sendSuccess(200, 'Booking recommendations retrieved', $cached, ['_cache' => true]);
    exit;
}

// ── 2. Sandbox fallback ───────────────────────────────────────────────────────
if (!AI_ENABLED) {
    $sandbox = buildBookingFallback([]);
    AiAuthMiddleware::logUsage($userId, $role, 'booking_recommendations', $db);
    sendSuccess(200, 'Sandbox recommendations generated', $sandbox, ['_sandbox' => true]);
    exit;
}

// ── 3. SQL aggregation layer ──────────────────────────────────────────────────
try {
    $now        = new DateTime();
    $currentTime = $now->format('Y-m-d H:i:s');
    $currentDay  = $now->format('l');

    // Per-lab peak hour blocks (top 5 busiest hours per lab, last 30 days)
    $stmt1 = $db->query("
        WITH ranked_occupancy AS (
            SELECT
                l.lab_code,
                l.name                                                          AS lab_name,
                EXTRACT(HOUR FROM s.time_in)                                    AS hour_block,
                COUNT(*)                                                        AS load_count,
                ROW_NUMBER() OVER (
                    PARTITION BY l.lab_code
                    ORDER BY COUNT(*) DESC
                )                                                               AS rnk
            FROM sit_in_logs s
            JOIN laboratories l ON s.lab_id = l.id
            WHERE s.time_in   >= NOW() - INTERVAL '30 days'
              AND s.deleted_at IS NULL
            GROUP BY l.lab_code, l.name, hour_block
        )
        SELECT lab_code, lab_name, hour_block, load_count
        FROM ranked_occupancy
        WHERE rnk <= 5
        ORDER BY lab_code, load_count DESC
    ");
    $occupancyLoad = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // Active labs with capacity + live PC count
    $stmt2 = $db->query("
        SELECT
            l.name, l.lab_code, l.capacity,
            COUNT(CASE WHEN p.pc_status = 'active' THEN 1 END) AS available_pcs
        FROM laboratories l
        LEFT JOIN pcs p ON p.lab_id = l.id
        WHERE l.is_active = TRUE AND l.deleted_at IS NULL
        GROUP BY l.id, l.name, l.lab_code, l.capacity
        ORDER BY l.lab_code
    ");
    $activeLabs = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Upcoming approved reservations (next 7 days) — grouped for density signal
    $stmt3 = $db->query("
        SELECT
            l.lab_code,
            r.reserved_date,
            r.reserved_time,
            COUNT(*)          AS reservation_count,
            MAX(l.capacity)   AS lab_capacity,
            ROUND(COUNT(*)::NUMERIC / NULLIF(MAX(l.capacity), 0) * 100, 1)
                              AS booking_pct
        FROM reservations r
        JOIN laboratories l ON r.lab_id = l.id
        WHERE r.status       = 'approved'
          AND r.reserved_date >= CURRENT_DATE
          AND r.reserved_date <= CURRENT_DATE + INTERVAL '7 days'
          AND r.deleted_at IS NULL
        GROUP BY l.lab_code, r.reserved_date, r.reserved_time
        ORDER BY r.reserved_date, r.reserved_time
        LIMIT 50
    ");
    $upcomingResLoad = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    // Student's own booking preference pattern (their historical preferred hours + labs)
    $stmt4 = $db->prepare("
        SELECT
            l.lab_code,
            EXTRACT(DOW  FROM s.time_in) AS preferred_dow,
            EXTRACT(HOUR FROM s.time_in) AS preferred_hour,
            COUNT(*)                     AS visit_count
        FROM sit_in_logs s
        JOIN laboratories l ON s.lab_id = l.id
        WHERE s.student_id  = :sid
          AND s.deleted_at IS NULL
        GROUP BY l.lab_code, preferred_dow, preferred_hour
        ORDER BY visit_count DESC
        LIMIT 5
    ");
    $stmt4->execute([':sid' => $studentId]);
    $studentPreferences = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    // ── 4. Build low-occupancy slot scoring (SQL math, no AI needed for the list)
    $stmt5 = $db->query("
        SELECT
            l.lab_code,
            l.name                                                             AS lab_name,
            EXTRACT(DOW  FROM s.time_in)                                       AS day_of_week,
            EXTRACT(HOUR FROM s.time_in)                                       AS hour_slot,
            COUNT(s.id)                                                        AS historical_sessions,
            l.capacity,
            ROUND(COUNT(s.id)::NUMERIC / NULLIF(l.capacity, 0) * 100, 1)      AS occupancy_pct,
            (SELECT COUNT(*) FROM pcs p
             WHERE p.lab_id = l.id AND p.pc_status = 'active')                AS available_pcs
        FROM laboratories l
        LEFT JOIN sit_in_logs s
               ON s.lab_id     = l.id
              AND s.time_in    >= CURRENT_DATE - INTERVAL '30 days'
              AND s.deleted_at IS NULL
        WHERE l.is_active = TRUE
          AND EXTRACT(HOUR FROM s.time_in) BETWEEN 7 AND 21
        GROUP BY l.id, l.name, l.lab_code, l.capacity, day_of_week, hour_slot
        ORDER BY occupancy_pct ASC
        LIMIT 10
    ");
    $lowOccupancySlots = $stmt5->fetchAll(PDO::FETCH_ASSOC);

    // ── 5. AI prompt — receives only aggregated rows, produces narrative summary
    $prompt  = "You are a helpful booking assistant for a university computer lab (CCS).\n";
    $prompt .= "Current System Time (Asia/Manila): {$currentTime} ({$currentDay})\n";
    $prompt .= "Operating Hours: Monday–Saturday, 07:30 AM – 09:30 PM. NEVER suggest slots outside this window.\n\n";
    $prompt .= "The SQL layer has already computed the 10 lowest-occupancy slots below. ";
    $prompt .= "Your job is NOT to recompute the list — it is to write a friendly, specific narrative summary and pick the top 3 from the list as structured recommendations.\n";
    $prompt .= "Output ONLY a raw JSON object. No markdown, no code blocks.\n\n";

    $prompt .= "## Active Labs (with live PC availability)\n";
    foreach ($activeLabs as $lab) {
        $prompt .= "- {$lab['lab_code']} ({$lab['name']}): {$lab['capacity']} seats, {$lab['available_pcs']} active PCs\n";
    }

    $prompt .= "\n## Pre-Computed Low-Occupancy Slots (already ranked by SQL, lowest occupancy first)\n";
    if (empty($lowOccupancySlots)) {
        $prompt .= "- No historical data available yet.\n";
    } else {
        foreach ($lowOccupancySlots as $slot) {
            $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            $day  = $days[(int)$slot['day_of_week']] ?? '?';
            $prompt .= "- {$slot['lab_code']} on {$day} at {$slot['hour_slot']}:00 — ";
            $prompt .= "{$slot['occupancy_pct']}% occupancy, {$slot['available_pcs']} PCs available\n";
        }
    }

    $prompt .= "\n## Student's Booking Preferences (their own historical pattern)\n";
    if (empty($studentPreferences)) {
        $prompt .= "- No prior booking history for this student.\n";
    } else {
        foreach ($studentPreferences as $pref) {
            $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            $day  = $days[(int)$pref['preferred_dow']] ?? '?';
            $prompt .= "- Prefers {$pref['lab_code']} on {$day} at {$pref['preferred_hour']}:00 ({$pref['visit_count']} visits)\n";
        }
    }

    $prompt .= "\n## Upcoming Reservation Pressure (next 7 days)\n";
    if (empty($upcomingResLoad)) {
        $prompt .= "- No major upcoming reservation clusters.\n";
    } else {
        foreach ($upcomingResLoad as $u) {
            $prompt .= "- {$u['lab_code']} on {$u['reserved_date']} at {$u['reserved_time']}: ";
            $prompt .= "{$u['reservation_count']} booked ({$u['booking_pct']}% of capacity)\n";
        }
    }

    $prompt .= "\n## Output Format\n";
    $prompt .= "Return a JSON object with exactly these keys:\n";
    $prompt .= "- 'slots': Array of exactly 3 objects from the low-occupancy list above. Each must have:\n";
    $prompt .= "  - 'time': 12-hour AM/PM format, e.g. '08:00 AM – 10:00 AM'\n";
    $prompt .= "  - 'lab': lab_code string\n";
    $prompt .= "  - 'occupancy_rate': 'Low' | 'Moderate' | 'High'\n";
    $prompt .= "  - 'available_pcs': integer\n";
    $prompt .= "  - 'reason': max 15 words, specific to this slot's data\n";
    $prompt .= "- 'summary': 2-3 sentence friendly narrative. Reference the student's preference pattern if available. Warn if their preferred slot is competitive.\n";
    $prompt .= "- 'preference_match': true if any recommended slot matches the student's historical preference, otherwise false.\n";
    $prompt .= "- 'pc_warning': String warning if any recommended slot has fewer than 5 available PCs, otherwise null.\n";

    $rawResponse = callGemini($prompt);

    // ── 6. Handle AI failure / rate limit ────────────────────────────────────
    if ($rawResponse === null) {
        $aiError  = getLastGeminiError();
        $fallback = buildBookingFallback($activeLabs);
        if ($aiError && $aiError['http_code'] === 429) {
            $fallback['is_fallback']     = true;
            $fallback['fallback_reason'] = 'Rate limit cooldown active';
            AiAuthMiddleware::logUsage($userId, $role, 'booking_recommendations', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving local recommendations.', $fallback);
            exit;
        }
        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to fetch recommendations from AI provider.', $isDev ? $aiError : null);
    }

    // ── 7. Parse and merge SQL data + AI narrative ───────────────────────────
    $cleanedJson = stripMarkdownFences($rawResponse);
    $aiData      = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($aiData['slots'])) {
        error_log('Gemini Booking Recommendations JSON Parse Error: ' . $rawResponse);
        sendError(500, 'AI response format was invalid. Please try again.');
    }

    // Merge: raw SQL slot list + AI narrative fields
    $payload = [
        'slots'            => $aiData['slots'],
        'summary'          => $aiData['summary']          ?? null,
        'preference_match' => $aiData['preference_match'] ?? false,
        'pc_warning'       => $aiData['pc_warning']       ?? null,
        'raw_slots'        => $lowOccupancySlots,          // full SQL list for frontend table
    ];

    // ── 8. Cache (4-hour TTL, keyed per student) ─────────────────────────────
    AiCache::set($db, $cacheKey, 'booking_recommendations', $payload, ttlHours: 4);
    AiAuthMiddleware::logUsage($userId, $role, 'booking_recommendations', $db);
    sendSuccess(200, 'AI booking recommendations retrieved successfully', $payload);

} catch (Exception $e) {
    sendError(500, 'Server error during recommendations extraction.', $e);
}

// ── Helper: build fallback response using real lab data where available ───────
function buildBookingFallback(array $activeLabs): array {
    return [
        'slots' => [
            [
                'time'          => '08:00 AM – 10:00 AM',
                'lab'           => $activeLabs[0]['lab_code'] ?? 'LAB 526',
                'occupancy_rate'=> 'Low',
                'available_pcs' => $activeLabs[0]['available_pcs'] ?? 30,
                'reason'        => 'Quiet morning slot with maximum seat availability.',
            ],
            [
                'time'          => '01:00 PM – 03:00 PM',
                'lab'           => $activeLabs[1]['lab_code'] ?? 'LAB 544',
                'occupancy_rate'=> 'Moderate',
                'available_pcs' => $activeLabs[1]['available_pcs'] ?? 25,
                'reason'        => 'Steady flow before the late afternoon rush.',
            ],
            [
                'time'          => '10:00 AM – 12:00 PM',
                'lab'           => $activeLabs[2]['lab_code'] ?? 'LAB 529',
                'occupancy_rate'=> 'Low',
                'available_pcs' => $activeLabs[2]['available_pcs'] ?? 28,
                'reason'        => 'Post-class dip in student attendance.',
            ],
        ],
        'summary'          => 'Labs are generally quietest before 1 PM and on Wednesday mornings. Book early to secure your preferred workstation.',
        'preference_match' => false,
        'pc_warning'       => null,
        'raw_slots'        => [],
    ];
}