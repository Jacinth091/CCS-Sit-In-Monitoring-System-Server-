<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$currentUser = requireAuth();

$studentModel = new Student($db);

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
    $errors = [];

    // Field-specific validation
    if (!Validator::isValidName($data->first_name)) {
        $errors['first_name'] = 'First name contains invalid characters.';
    }
    if (!Validator::isValidName($data->last_name)) {
        $errors['last_name'] = 'Last name contains invalid characters.';
    }
    if (!empty($data->middle_name) && !Validator::isValidName($data->middle_name)) {
        $errors['middle_name'] = 'Middle name contains invalid characters.';
    }
    if (!Validator::validateEmail($data->email)) {
        $errors['email'] = 'Invalid email address format.';
    }
    if (!empty($data->address) && !Validator::isValidAddress($data->address)) {
        $errors['address'] = "Invalid address format.";
    }

    if (!empty($errors)) {
        sendValidationError($errors);
    }

    $studentModel->id           = $targetId;
    
    // Fetch current student_id to preserve it (students cannot change their own ID)
    $stmt = $db->prepare("SELECT student_id FROM students WHERE id = :id");
    $stmt->execute([':id' => $targetId]);
    $existing = $stmt->fetch();
    $studentModel->student_id = $existing['student_id'];

    $studentModel->first_name   = $data->first_name;
    $studentModel->last_name    = $data->last_name;
    $studentModel->middle_name  = $data->middle_name  ?? '';
    $studentModel->course       = $data->course       ?? '';
    $studentModel->course_level = $data->course_level ?? '';
    $studentModel->email        = $data->email;
    $studentModel->address      = $data->address      ?? '';
    $studentModel->session      = $data->session      ?? 30;
    $studentModel->profile_pic  = $data->profile_pic  ?? null;

    // Uniqueness checks (excluding current student)
    if ($studentModel->emailExist($targetId)) {
        sendValidationError(['email' => 'This email address is already assigned to another student.']);
    }

    if($studentModel->update()) {
        sendSuccess(200, 'Student profile updated successfully.');
    } else {
        sendError(500, 'Failed to update student profile.');
    }
} else {
    sendError(400, 'Required fields are missing (first_name, last_name, email).');
}
