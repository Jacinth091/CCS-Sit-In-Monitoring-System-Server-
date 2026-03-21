<?php

require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$sitIn = new SitIn($db);
$data  = json_decode(file_get_contents('php://input'));

if (empty($data->student_id) || empty($data->lab_id) || empty($data->purpose)) {
    http_response_code(400);
    echo json_encode(['message' => 'student_id, lab_id and purpose are required.']);
    exit();
}

// Check if student already has an ongoing session
$sitIn->student_id = $data->student_id;
$ongoing = $sitIn->getOngoingSession();

if ($ongoing) {
    http_response_code(409);
    echo json_encode(['message' => 'Student already has an ongoing sit-in session.']);
    exit();
}

$sitIn->lab_id    = $data->lab_id;
$sitIn->purpose   = $data->purpose;

$id = $sitIn->timeIn();

if ($id) {
    http_response_code(201);
    echo json_encode([
        'message' => 'Time-in recorded successfully.',
        'log_id'  => $id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to record time-in.']);
}