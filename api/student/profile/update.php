<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$currentUser = requireAuth();

$student = new Student($db);

// Read JSON body from PUT request
$data = json_decode(file_get_contents("php://input"));

// If ID is not in the request body, use the authenticated user's ID
$targetId = !empty($data->id) ? $data->id : $currentUser->id;

// Students can only update their own profile; admins can update for anyone
if ($currentUser->role === 'student' && $targetId !== $currentUser->id) {
    sendError(403, 'Access denied. You can only update your own profile.');
}

if(
    !empty($targetId) &&
    !empty($data->first_name) &&
    !empty($data->last_name) &&
    !empty($data->email)
) {
    $student->id           = $targetId;
    $student->first_name   = $data->first_name;
    $student->last_name    = $data->last_name;
    $student->middle_name  = $data->middle_name  ?? '';
    $student->course       = $data->course       ?? '';
    $student->course_level = $data->course_level ?? '';
    $student->email        = $data->email;
    $student->address      = $data->address      ?? '';
    $student->session      = $data->session      ?? 30;
    $student->profile_pic  = $data->profile_pic  ?? null;

    if($student->update()) {
        sendSuccess(200, 'Student profile updated successfully.');
    } else {
        sendError(500, 'Failed to update student profile.');
    }
} else {
    sendError(400, 'Required fields are missing (first_name, last_name, email).');
}
