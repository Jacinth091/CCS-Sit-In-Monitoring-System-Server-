<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

try {
    $stats = [];

    // Total records
    $totalStmt = $db->query("SELECT COUNT(*) FROM sit_in_logs WHERE deleted_at IS NULL");
    $stats['total_records'] = (int)$totalStmt->fetchColumn();

    // Active sessions
    $activeStmt = $db->query("SELECT COUNT(*) FROM sit_in_logs WHERE status = 'ongoing' AND deleted_at IS NULL");
    $stats['active_now'] = (int)$activeStmt->fetchColumn();

    // Average duration (in minutes) for completed sessions
    // For PostgreSQL, we subtract timestamps to get an interval
    $avgDurationStmt = $db->query("
        SELECT 
            ROUND(AVG(EXTRACT(EPOCH FROM (time_out - time_in)) / 60)) 
        FROM sit_in_logs 
        WHERE status = 'completed' AND deleted_at IS NULL
    ");
    $stats['avg_duration_minutes'] = (int)$avgDurationStmt->fetchColumn();

    // Most used lab
    $mostUsedLabStmt = $db->query("
        SELECT l.lab_name
        FROM sit_in_logs sl
        JOIN laboratories l ON sl.lab_id = l.id
        WHERE sl.deleted_at IS NULL
        GROUP BY l.lab_name
        ORDER BY COUNT(*) DESC
        LIMIT 1
    ");
    $stats['most_used_lab'] = $mostUsedLabStmt->fetchColumn() ?: 'N/A';

    sendSuccess(200, 'Sit-in statistics retrieved successfully.', $stats);

} catch (Exception $e) {
    sendError(500, 'Database error occurred while fetching sit-in statistics.', $e);
}
