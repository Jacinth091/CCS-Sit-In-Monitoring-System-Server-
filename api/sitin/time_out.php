<?php

require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$currentUser = requireAuth();

$sitIn = new SitIn($db);
$data  = json_decode(file_get_contents('php://input'));

// Students can only time-out for themselves
if ($currentUser->role === 'student' && isset($data->student_id) && $data->student_id !== $currentUser->student_id) {
    sendError(403, 'Access denied. You can only time-out for yourself.');
}

if (empty($data->log_id) || empty($data->student_id)) {
    http_response_code(400);
    echo json_encode(['message' => 'log_id and student_id are required.']);
    exit();
}

$sitIn->id         = $data->log_id;
$sitIn->student_id = $data->student_id;

if ($sitIn->timeOut()) {
    http_response_code(200);
    echo json_encode(['message' => 'Time-out recorded successfully.']);
} else {
    http_response_code(404);
    echo json_encode(['message' => 'No ongoing session found for this student.']);
}