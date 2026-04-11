<?php
/**
 * GET /api/student/sitin/stats.php
 * Purpose: Return the student's personal aggregated stats for the summary cards.
 */

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$student = requireStudent();
$student_id = $student->student_id;

try {
    $query = "
        SELECT
            COUNT(*)                                                        AS total_sessions,
            COALESCE(
                ROUND(
                    SUM(EXTRACT(EPOCH FROM (time_out - time_in)) / 60)
                    FILTER (WHERE time_out IS NOT NULL)
                ) / 60.0,
                0
            )                                                               AS total_hours,
            (SELECT lab_name FROM laboratories WHERE id = (
                SELECT lab_id FROM sit_in_logs 
                WHERE student_id = :student_id 
                GROUP BY lab_id 
                ORDER BY COUNT(*) DESC 
                LIMIT 1
            ))                                                              AS most_visited_lab,
            MAX(time_in::date)                                              AS last_session_date
        FROM sit_in_logs
        WHERE student_id = :student_id
        AND deleted_at IS NULL;
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $student_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format numbers
    $stats['total_sessions'] = (int)$stats['total_sessions'];
    $stats['total_hours'] = (float)$stats['total_hours'];

    sendSuccess(200, 'Student stats fetched successfully.', $stats);

} catch (Exception $e) {
    sendError(500, 'Failed to fetch student stats.', $e);
}
