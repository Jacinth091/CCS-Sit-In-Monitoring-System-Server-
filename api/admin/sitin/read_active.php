<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

try {
    $stmt = $db->prepare("
        SELECT
            sl.id,
            sl.id           AS log_id,
            sl.purpose,
            sl.time_in,
            sl.status,
            s.student_id,
            s.first_name,
            s.last_name,
            s.profile_pic,
            s.course,
            s.course_level,
            s.session,
            l.name,
            l.lab_code,
            sl.pc_number
        FROM sit_in_logs sl
        INNER JOIN students     s ON sl.student_id = s.student_id
        INNER JOIN laboratories l ON sl.lab_id     = l.id
        WHERE sl.status     = 'ongoing'
        AND   sl.deleted_at IS NULL
        ORDER BY sl.time_in DESC
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode($logs);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to fetch active sessions.', 'details' => $e->getMessage()]);
}
