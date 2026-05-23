<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

$studentModel = new Student($db);

// Read JSON body from PUT request
$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->id) &&
    !empty($data->student_id) &&
    !empty($data->first_name) &&
    !empty($data->last_name) &&
    !empty($data->email)
) {
    $errors = [];

    // Field-specific validation
    if (!Validator::isValidStudentId($data->student_id)) {
        $errors['student_id'] = 'Student ID must be exactly 8 digits.';
    }
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

    $auditLog = new AuditLog($db);
    $studentModel->id           = $data->id;
    $studentModel->student_id   = $data->student_id;
    $studentModel->first_name   = $data->first_name;
    $studentModel->last_name    = $data->last_name;
    $studentModel->middle_name  = $data->middle_name  ?? '';
    $studentModel->course       = $data->course       ?? '';
    $studentModel->course_level = $data->course_level ?? '';
    $studentModel->email        = $data->email;
    $studentModel->address      = $data->address      ?? '';
    $studentModel->session      = $data->session      ?? 30;
    $studentModel->profile_pic  = $data->profile_pic  ?? null;
    $studentModel->is_active    = isset($data->is_active) ? (bool)$data->is_active : true;

    // Uniqueness checks (excluding current student)
    if ($studentModel->studentIdExist($data->student_id, $data->id)) {
        sendValidationError(['student_id' => 'This Student ID is already assigned to another student.']);
    }

    if ($studentModel->emailExist($data->id)) {
        sendValidationError(['email' => 'This email address is already assigned to another student.']);
    }

    // Get old student ID for logging
    $stmt = $db->prepare("SELECT student_id FROM students WHERE id = :id");
    $stmt->execute([':id' => $data->id]);
    $existing = $stmt->fetch();
    $oldSid = $existing['student_id'] ?? 'Unknown';

    if($studentModel->update()) {
        $name = "$data->first_name $data->last_name";
        $logMessage = "Admin updated profile for student: $name ($oldSid)";
        if ($oldSid !== $data->student_id) {
            $logMessage .= ". Student ID changed from $oldSid to $data->student_id";
        }

        // Log to unified audit log
        $auditLog->write(
            'Student updated',
            $admin->student_id,
            'admin',
            $logMessage,
            'student',
            (string)$data->student_id,
            null,
            "Updated student $data->student_id"
        );

        sendSuccess(200, 'Student updated successfully by administrator.');
    } else {
        sendError(500, 'Failed to update student record.');
    }
} else {
    sendError(400, 'Required fields are missing (id, student_id, first_name, last_name, email).');
}
