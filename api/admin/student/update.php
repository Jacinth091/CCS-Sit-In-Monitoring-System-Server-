<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$student = new Student($db);

// Read JSON body from PUT request
$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->id) &&
    !empty($data->first_name) &&
    !empty($data->last_name) &&
    !empty($data->email)
) {
    $student->id           = $data->id;
    $student->first_name   = $data->first_name;
    $student->last_name    = $data->last_name;
    $student->middle_name  = $data->middle_name  ?? '';
    $student->course       = $data->course       ?? '';
    $student->course_level = $data->course_level ?? '';
    $student->email        = $data->email;
    $student->address      = $data->address      ?? '';
    $student->session      = $data->session      ?? 30;
    $student->profile_pic  = $data->profile_pic  ?? null;
    $student->is_active    = isset($data->is_active) ? $data->is_active : true;

    if($student->update()) {
        sendSuccess(200, 'Student updated successfully by administrator.');
    } else {
        sendError(500, 'Failed to update student record.');
    }
} else {
    sendError(400, 'Required fields are missing (id, first_name, last_name, email).');
}
