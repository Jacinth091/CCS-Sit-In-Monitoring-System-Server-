<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$student = requireStudent();

// Receive JSON payload
$data = json_decode(file_get_contents("php://input"));

// Validation
$errors = [];
if (empty($data->sit_in_id)) {
    $errors['sit_in_id'] = 'Sit-in ID is required.';
}
if (empty($data->rating) || $data->rating < 1 || $data->rating > 5) {
    $errors['rating'] = 'Rating must be between 1 and 5.';
}
if (isset($data->comment) && strlen($data->comment) > 500) {
    $errors['comment'] = 'Comment must not exceed 500 characters.';
}

if (!empty($errors)) {
    sendValidationError($errors);
}

try {
    // 1. Verify sit_in_id exists and belongs to this student
    $checkStmt = $db->prepare("SELECT id FROM sit_in_logs WHERE id = :id AND student_id = :student_id AND deleted_at IS NULL");
    $checkStmt->execute([
        ':id' => $data->sit_in_id,
        ':student_id' => $student->student_id
    ]);
    $sitIn = $checkStmt->fetch();

    if (!$sitIn) {
        sendError(404, 'Sit-in record not found or does not belong to you.');
    }

    // 2. Upsert feedback
    // Note: The unique constraint on sit_in_id allows us to use ON CONFLICT
    $query = "
        INSERT INTO feedback (sit_in_id, student_id, rating, comment, updated_at)
        VALUES (:sit_in_id, :student_id, :rating, :comment, CURRENT_TIMESTAMP)
        ON CONFLICT (sit_in_id) 
        DO UPDATE SET 
            rating = EXCLUDED.rating,
            comment = EXCLUDED.comment,
            updated_at = CURRENT_TIMESTAMP
        RETURNING *
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':sit_in_id' => $data->sit_in_id,
        ':student_id' => $student->student_id,
        ':rating' => (int)$data->rating,
        ':comment' => !empty($data->comment) ? Validator::sanitizeString($data->comment) : null
    ]);

    $feedback = $stmt->fetch();

    sendSuccess(200, 'Feedback saved successfully.', $feedback);

} catch (Exception $e) {
    sendError(500, 'Failed to save feedback.', $e);
}
