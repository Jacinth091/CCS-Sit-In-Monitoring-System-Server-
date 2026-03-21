<?php
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

// Check if user is admin (optional, can add token check here)

try {
    $stats = array();

    // 1. Total Students Registered
    $stmt = $db->query('SELECT COUNT(*) as count FROM students WHERE is_active = TRUE');
    $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 2. Currently Sit-in
    $stmt = $db->query("SELECT COUNT(*) as count FROM sit_in_logs WHERE status = 'Active'");
    $stats['current_sitin'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 3. Total Sit-in
    $stmt = $db->query("SELECT COUNT(*) as count FROM sit_in_logs");
    $stats['total_sitin'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 4. Programming Languages / Purpose Distribution (for the pie chart)
    $stmt = $db->query("SELECT COALESCE(NULLIF(TRIM(purpose), ''), 'Unknown') as label, COUNT(*) as count FROM sit_in_logs GROUP BY label ORDER BY count DESC LIMIT 5");
    $purposes = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        array_push($purposes, $row);
    }
    $stats['course_distribution'] = $purposes;

    http_response_code(200);
    echo json_encode($stats);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
}
