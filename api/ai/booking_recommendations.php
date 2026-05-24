<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/gemini.php';
require_once '../../src/middleware/AiAuthMiddleware.php';

$input = json_decode(file_get_contents("php://input"), true) ?? [];

// Guard the AI endpoint
$auth = AiAuthMiddleware::guard('booking_recommendations', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

// Auth: Students only
$currentUser = requireStudent();

// Sandbox Mode Check
if (!AI_ENABLED) {
    $sandboxResponse = [
        'recommended_slots' => [
            [
                'time' => '08:00 AM - 10:00 AM',
                'lab' => 'LAB 526',
                'occupancy_rate' => 'Low',
                'reason' => 'Quiet morning slot with maximum seat availability.'
            ],
            [
                'time' => '01:00 PM - 03:00 PM',
                'lab' => 'LAB 544',
                'occupancy_rate' => 'Moderate',
                'reason' => 'Steady occupancy flow before the late afternoon rush.'
            ],
            [
                'time' => '10:00 AM - 12:00 PM',
                'lab' => 'LAB 529',
                'occupancy_rate' => 'Low',
                'reason' => 'Post-class dip in student attendance.'
            ]
        ],
        'recommendations' => [
            "LAB 526 and LAB 528 experience peak loads between 3 PM and 6 PM. Book before 1 PM for a quieter workspace.",
            "LAB 544 is highly demanded for database and network development; morning bookings guarantee instant PC allocations.",
            "Labs 529 and 542 have consistent open slots during mid-day blocks (11 AM to 1 PM)."
        ]
    ];
    AiAuthMiddleware::logUsage($userId, $role, 'booking_recommendations', $db);
    sendSuccess(200, 'Sandbox recommendations generated', $sandboxResponse, ['_sandbox' => true]);
}

// Live AI Inference
try {
    // Get current system time context
    $now = new DateTime();
    $currentTime = $now->format('Y-m-d H:i:s');
    $currentDay = $now->format('l');

    // Get sit-in hourly logs count for past 30 days (grouped by lab to ensure all labs get represented if they have data)
    // We'll take the top 5 busiest slots PER LAB instead of a global limit
    $stmt1 = $db->query("
        WITH ranked_occupancy AS (
            SELECT 
                l.lab_code,
                EXTRACT(HOUR FROM s.time_in) AS hour_block,
                COUNT(*) AS load_count,
                ROW_NUMBER() OVER(PARTITION BY l.lab_code ORDER BY COUNT(*) DESC) as rank
            FROM sit_in_logs s
            JOIN laboratories l ON s.lab_id = l.id
            WHERE s.time_in >= NOW() - INTERVAL '30 days'
              AND s.deleted_at IS NULL
            GROUP BY l.lab_code, hour_block
        )
        SELECT lab_code, hour_block, load_count 
        FROM ranked_occupancy 
        WHERE rank <= 5
        ORDER BY lab_code, load_count DESC
    ");
    $occupancyLoad = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // Get laboratory capacities & lists
    $stmt2 = $db->query("
        SELECT name, lab_code, capacity, is_active FROM laboratories WHERE is_active = TRUE AND deleted_at IS NULL
    ");
    $activeLabs = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Get upcoming confirmed reservations (next 7 days)
    $stmt3 = $db->query("
        SELECT 
            l.lab_code,
            r.reserved_date,
            r.reserved_time,
            COUNT(*) AS reservation_count
        FROM reservations r
        JOIN laboratories l ON r.lab_id = l.id
        WHERE r.status = 'approved' 
          AND r.reserved_date >= CURRENT_DATE 
          AND r.reserved_date <= CURRENT_DATE + INTERVAL '7 days'
          AND r.deleted_at IS NULL
        GROUP BY l.lab_code, r.reserved_date, r.reserved_time
        ORDER BY r.reserved_date, r.reserved_time
        LIMIT 50
    ");
    $upcomingResLoad = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $prompt = "You are an expert analytical assistant for the CCS Laboratory Monitoring System.\n";
    $prompt .= "Current System Time (Asia/Manila): " . $currentTime . " (" . $currentDay . ")\n";
    $prompt .= "Operating Hours: Monday to Saturday, 07:30 AM to 09:30 PM. NEVER recommend slots outside this window.\n\n";
    $prompt .= "Analyze the laboratory capacities and historical usage data below to recommend the best, off-peak timeslots and labs for new reservations.\n";
    $prompt .= "IMPORTANT: If a specific hour or lab is NOT mentioned in the load sections, it means it has LOW or NO recorded activity. Do not claim a lab has 'no historical data' if it appears in the occupancy list at all; instead, focus on the specific quiet timeslots.\n";
    $prompt .= "Your output must be EXACTLY a raw JSON object. No markdown, no filler.\n\n";

    $prompt .= "## Laboratory Capacities\n";
    foreach ($activeLabs as $lab) {
        $prompt .= "- " . $lab['lab_code'] . ": " . $lab['capacity'] . " seats available.\n";
    }
    $prompt .= "\n";

    $prompt .= "## Peak Historical Occupancy (Past 30 Days)\n";
    $prompt .= "The following are the known BUSY hour blocks for each lab. If an hour is not listed, it is historically quiet.\n";
    if (empty($occupancyLoad)) {
        $prompt .= "- No significant peak logs recorded recently.\n";
    } else {
        foreach ($occupancyLoad as $o) {
            $prompt .= "- " . $o['lab_code'] . " is busy at " . sprintf('%02d:00', $o['hour_block']) . " (Avg " . $o['load_count'] . " students)\n";
        }
    }
    $prompt .= "\n";

    $prompt .= "## Upcoming Approved Reservations (Next 7 Days)\n";
    if (empty($upcomingResLoad)) {
        $prompt .= "- No major upcoming reservation clusters.\n";
    } else {
        foreach ($upcomingResLoad as $u) {
            $prompt .= "- " . $u['lab_code'] . " on " . $u['reserved_date'] . " at " . $u['reserved_time'] . ": " . $u['reservation_count'] . " seats booked.\n";
        }
    }
    $prompt .= "\n";

    $prompt .= "## Instructions for JSON structure:\n";
    $prompt .= "Generate a JSON object with 'recommended_slots' (3 items) and 'recommendations' (2-3 items).\n";
    $prompt .= "- 'recommended_slots' keys: 'time' (MUST be strict 12-hour format with AM/PM for Asia/Manila, e.g. '08:00 AM - 10:00 AM' or '07:00 PM - 09:00 PM'. NEVER use 24-hour mixed with PM like '19:00 PM'), 'lab', 'occupancy_rate' (Low/Moderate/High), 'reason' (max 15 words).\n";
    $prompt .= "- 'recommendations': Descriptive bullet points (max 25 words each) focusing on general trends and best practices for the student.\n\n";
    $prompt .= "Be precise. Use the Asia/Manila Current System Time to ensure recommendations are for the future. Only suggest times within the Operating Hours (until 09:30 PM).\n";

    $rawResponse = callGemini($prompt);

    if ($rawResponse === null) {
        $aiError = getLastGeminiError();
        if ($aiError && $aiError['http_code'] === 429) {
            // Rate limited! Fallback to sandbox recommendations gracefully
            $sandboxResponse = [
                'recommended_slots' => [
                    [
                        'time' => '08:00 AM - 10:00 AM',
                        'lab' => !empty($activeLabs) ? $activeLabs[0]['lab_code'] : 'LAB 526',
                        'occupancy_rate' => 'Low',
                        'reason' => 'Quiet morning slot with maximum seat availability.'
                    ],
                    [
                        'time' => '01:00 PM - 03:00 PM',
                        'lab' => count($activeLabs) > 1 ? $activeLabs[1]['lab_code'] : 'LAB 544',
                        'occupancy_rate' => 'Moderate',
                        'reason' => 'Steady occupancy flow before the late afternoon rush.'
                    ],
                    [
                        'time' => '10:00 AM - 12:00 PM',
                        'lab' => count($activeLabs) > 2 ? $activeLabs[2]['lab_code'] : 'LAB 529',
                        'occupancy_rate' => 'Low',
                        'reason' => 'Post-class dip in student attendance.'
                    ]
                ],
                'recommendations' => [
                    "Laboratories experience peak loads between 3 PM and 6 PM. Book before 1 PM for a quieter workspace.",
                    "Morning bookings generally guarantee instant PC allocations and lower occupancy blocks across all laboratories."
                ],
                'is_fallback' => true,
                'fallback_reason' => 'Rate limit cooldown active'
            ];
            AiAuthMiddleware::logUsage($userId, $role, 'booking_recommendations', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving local recommendations.', $sandboxResponse);
        }

        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to fetch recommendations from AI provider.', $isDev ? $aiError : null);
    }

    $cleanedJson = stripMarkdownFences($rawResponse);
    $recommendationsData = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($recommendationsData) || !isset($recommendationsData['recommended_slots'])) {
        error_log("Gemini Recommendations JSON Parse Error on: " . $rawResponse);
        sendError(500, 'AI response format was invalid: ' . $rawResponse);
    }

    AiAuthMiddleware::logUsage($userId, $role, 'booking_recommendations', $db);
    sendSuccess(200, 'AI booking recommendations retrieved successfully', $recommendationsData);

} catch (Exception $e) {
    sendError(500, 'Server error during recommendations extraction.', $e);
}
