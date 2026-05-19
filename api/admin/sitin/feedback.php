<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

// For POST/PUT/PATCH, we often receive JSON
$data = json_decode(file_get_contents("php://input"));

// Validation
$errors = [];
if (empty($data->sit_in_id)) {
    $errors['sit_in_id'] = 'Sit-in ID is required.';
}
if (empty($data->feedback_text)) {
    $errors['feedback_text'] = 'Feedback text is required.';
} elseif (strlen($data->feedback_text) > 500) {
    $errors['feedback_text'] = 'Feedback text must not exceed 500 characters.';
}

if (!empty($errors)) {
    sendValidationError($errors);
}

try {
    $auditLog = new AuditLog($db);
    // 1. Verify sit_in_id exists
    $checkStmt = $db->prepare("SELECT id, student_id, time_in, lab_id FROM sit_in_logs WHERE id = :id AND deleted_at IS NULL");
    $checkStmt->execute([':id' => $data->sit_in_id]);
    $sitIn = $checkStmt->fetch();

    if (!$sitIn) {
        sendError(404, 'Sit-in record not found.');
    }

    // 2. Upsert feedback
    $query = "
        INSERT INTO admin_feedback (sit_in_id, admin_username, feedback_text, updated_at)
        VALUES (:sit_in_id, :admin_username, :feedback_text, CURRENT_TIMESTAMP)
        ON CONFLICT (sit_in_id) 
        DO UPDATE SET 
            feedback_text = EXCLUDED.feedback_text,
            updated_at = CURRENT_TIMESTAMP
        RETURNING *
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':sit_in_id' => $data->sit_in_id,
        ':admin_username' => $admin->student_id, 
        ':feedback_text' => Validator::sanitizeString($data->feedback_text)
    ]);

    $feedback = $stmt->fetch();

    // Log to unified audit log
    $sid = $sitIn['student_id'];
    $auditLog->write(
        'Feedback submitted',
        $admin->student_id,
        'admin',
        "Admin submitted feedback for student $sid on session #{$data->sit_in_id}",
        'sit_in_log',
        (string)$data->sit_in_id,
        $sitIn['lab_id'],
        "Submitted feedback for student $sid"
    );

    // 3. Notify student
    create_notification(
        $db,
        $sitIn['student_id'],
        $admin->student_id,
        'feedback',
        'Feedback Received',
        "The lab admin left feedback on your " . date('M d, Y', strtotime($sitIn['time_in'] ?? 'now')) . " session.",
        $data->sit_in_id,
        'sit_in_log'
    );

    sendSuccess(200, 'Feedback saved successfully.', $feedback);

} catch (Exception $e) {
    sendError(500, 'Failed to save feedback.', $e);
}
