<?php
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

$currentUser = requireAuth();

$student = new Student($db);

// Read JSON body from PUT request
$data = json_decode(file_get_contents("php://input"));

// Students can only update their own profile
if ($currentUser->role === 'student' && !empty($data->id) && $data->id !== $currentUser->id) {
    sendError(403, 'Access denied. You can only update your own profile.');
}

if(
    !empty($data->id) &&
    !empty($data->first_name) &&
    !empty($data->last_name) &&
    !empty($data->email)
) {
    $student->id           = $data->id;
    $student->student_id   = $data->student_id;
    $student->first_name   = $data->first_name;
    $student->last_name    = $data->last_name;
    $student->middle_name  = $data->middle_name  ?? '';
    $student->course       = $data->course       ?? '';
    $student->course_level = $data->course_level ?? '';
    $student->email        = $data->email;
    $student->address      = $data->address      ?? '';

    if($student->update()) {
        http_response_code(200);
        echo json_encode(['message' => 'Student updated successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to update student.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Missing required fields.']);
}