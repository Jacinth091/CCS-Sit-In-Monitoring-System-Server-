<?php

require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$currentUser = requireAuth();

if (empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Record ID is required.']);
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT
            sl.id           AS log_id,
            sl.purpose,
            sl.time_in,
            sl.time_out,
            sl.status,
            s.student_id,
            s.first_name,
            s.last_name,
            s.course,
            s.course_level,
            s.profile_pic,
            l.lab_name
        FROM sit_in_logs sl
        LEFT JOIN students     s ON sl.student_id = s.student_id
        LEFT JOIN laboratories l ON sl.lab_id     = l.id
        WHERE sl.id         = :id
        AND   sl.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $_GET['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        http_response_code(200);
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Record not found.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to fetch record.', 'details' => $e->getMessage()]);
}