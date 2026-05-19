<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

$student = new Student($db);

// Read JSON body from PUT request
$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->id) &&
    !empty($data->first_name) &&
    !empty($data->last_name) &&
    !empty($data->email)
) {
    $auditLog = new AuditLog($db);
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
    $student->is_active    = isset($data->is_active) ? (bool)$data->is_active : true;

    // Get old student ID for logging if needed, or just use the current one
    $stmt = $db->prepare("SELECT student_id FROM students WHERE id = :id");
    $stmt->execute([':id' => $data->id]);
    $existing = $stmt->fetch();
    $sid = $existing['student_id'] ?? 'Unknown';

    if($student->update()) {
        $name = "$data->first_name $data->last_name";
        // Log to unified audit log
        $auditLog->write(
            'Student updated',
            $admin->student_id,
            'admin',
            "Admin updated profile for student: $name ($sid)",
            'student',
            (string)$sid,
            null,
            "Updated student $sid"
        );

        sendSuccess(200, 'Student updated successfully by administrator.');
    } else {
        sendError(500, 'Failed to update student record.');
    }
} else {
    sendError(400, 'Required fields are missing (id, first_name, last_name, email).');
}
