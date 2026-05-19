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
                ),
                0
            )                                                               AS total_minutes,
            COALESCE(
                ROUND(
                    AVG(EXTRACT(EPOCH FROM (time_out - time_in)) / 60)
                    FILTER (WHERE time_out IS NOT NULL)
                ),
                0
            )                                                               AS avg_minutes,
            COALESCE(
                ROUND(
                    MAX(EXTRACT(EPOCH FROM (time_out - time_in)) / 60)
                    FILTER (WHERE time_out IS NOT NULL)
                ),
                0
            )                                                               AS longest_minutes,
            (SELECT CASE 
                    WHEN lab_code IS NOT NULL AND lab_code != '' THEN lab_code || ' - ' || name
                    ELSE name
                END 
                FROM laboratories WHERE id = (
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

    // Format total duration
    $total_minutes = (int)$stats['total_minutes'];
    $h = floor($total_minutes / 60);
    $m = $total_minutes % 60;
    $stats['total_duration'] = "{$h}h {$m}m";
    $stats['total_sessions'] = (int)$stats['total_sessions'];
    
    // Format average duration
    $avg = (int)$stats['avg_minutes'];
    $stats['avg_duration'] = $avg > 0 ? (floor($avg / 60) > 0 ? floor($avg / 60) . 'h ' . ($avg % 60) . 'm' : $avg . 'm') : null;

    // Format longest session duration
    $longest = (int)$stats['longest_minutes'];
    $stats['longest_duration'] = $longest > 0 ? (floor($longest / 60) > 0 ? floor($longest / 60) . 'h ' . ($longest % 60) . 'm' : $longest . 'm') : null;
    
    // Also include total_hours as a float just in case
    $stats['total_hours'] = round($total_minutes / 60.0, 1);

    sendSuccess(200, 'Student stats fetched successfully.', $stats);

} catch (Exception $e) {
    sendError(500, 'Failed to fetch student stats.', $e);
}
