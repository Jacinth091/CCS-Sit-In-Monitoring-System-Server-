<?php

require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

try {
    $stmt = $db->prepare("
        SELECT 
            sl.id           AS log_id,
            sl.student_id,
            sl.lab_id,
            sl.purpose,
            sl.time_in,
            sl.time_out,
            sl.status,
            s.first_name,
            s.last_name,
            s.profile_pic,
            s.course,
            s.course_level,
            l.lab_name
        FROM sit_in_logs sl
        JOIN students     s ON sl.student_id = s.student_id
        JOIN laboratories l ON sl.lab_id     = l.id
        WHERE sl.deleted_at IS NULL
        ORDER BY sl.time_in DESC
    ");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($records as &$rec) {
        $rec['profile_pic'] = $rec['profile_pic'] ?? '';
    }

    http_response_code(200);
    echo json_encode($records);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to fetch records.', 'details' => $e->getMessage()]);
}