<?php

require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$currentUser = requireAuth();
// Students can only view their own records
if ($currentUser->role === 'student' && isset($_GET['student_id']) && $_GET['student_id'] !== $currentUser->student_id) {
    sendError(403, 'Access denied. You can only view your own sit-in records.');
}

if (empty($_GET['student_id'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing student ID.']);
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
            l.lab_name
        FROM sit_in_logs sl
        LEFT JOIN laboratories l ON sl.lab_id = l.id
        WHERE sl.student_id = :student_id
        AND   sl.deleted_at IS NULL
        ORDER BY sl.time_in DESC
    ");
    $stmt->execute([':student_id' => $_GET['student_id']]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode($logs);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to fetch student records.', 'details' => $e->getMessage()]);
}