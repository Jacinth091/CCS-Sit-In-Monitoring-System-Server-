<?php
/**
 * GET /api/student/sitin/read_single.php
 * Purpose: Return a single sit-in record for the student, including full feedback text if it exists.
 */

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$student = requireStudent();
$student_id = $student->student_id;

if (empty($_GET['id'])) {
    sendError(400, 'Record ID is required.');
}

$record_id = $_GET['id'];

try {
    $query = "
        SELECT
            sl.id,
            sl.purpose,
            l.lab_name,
            sl.time_in,
            sl.time_out,
            sl.status,
            CASE
                WHEN sl.time_out IS NOT NULL THEN
                    ROUND(EXTRACT(EPOCH FROM (sl.time_out - sl.time_in)) / 60)
                ELSE NULL
            END AS duration_minutes,
            sf.feedback_text,
            sf.id AS feedback_id,
            sf.created_at AS feedback_date
        FROM sit_in_logs sl
        LEFT JOIN laboratories l ON sl.lab_id = l.id
        LEFT JOIN admin_feedback sf ON sf.sit_in_id = sl.id
        WHERE sl.id = :id AND sl.student_id = :student_id AND sl.deleted_at IS NULL;
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $record_id, ':student_id' => $student_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        sendError(404, 'Record not found.');
    }

    sendSuccess(200, 'Sit-in record fetched successfully.', $record);

} catch (Exception $e) {
    sendError(500, 'Failed to fetch sit-in record.', $e);
}
