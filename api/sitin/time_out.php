<?php

require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$sitIn = new SitIn($db);
$data  = json_decode(file_get_contents('php://input'));

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