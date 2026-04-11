<?php
/**
 * PATCH /api/student/notifications/mark_read.php
 * Purpose: Mark a single notification as read.
 */

require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

// Check if it's a PATCH request
if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    // Some environments don't support PATCH easily, let's allow POST as fallback if needed,
    // but for now strictly follow the plan or at least allow it.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError(405, 'Method not allowed. Use PATCH or POST.');
    }
}

$student = requireStudent();
$student_id = $student->student_id;

if (empty($_GET['id'])) {
    sendError(400, 'Notification ID is required.');
}

$id = (int)$_GET['id'];

try {
    $stmt = $db->prepare("
        UPDATE notifications
        SET is_read = TRUE
        WHERE id = :id AND student_id = :student_id
        RETURNING id, is_read
    ");
    $stmt->execute([':id' => $id, ':student_id' => $student_id]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$updated) {
        sendError(404, 'Notification not found or access denied.');
    }

    sendSuccess(200, 'Notification marked as read.', $updated);

} catch (Exception $e) {
    sendError(500, 'Failed to mark notification as read.', $e);
}
