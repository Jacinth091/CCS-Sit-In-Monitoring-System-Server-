<?php
/**
 * PATCH /api/student/notifications/mark_all_read.php
 * Purpose: Mark all of the student's unread notifications as read.
 */

require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError(405, 'Method not allowed. Use PATCH or POST.');
}

$student = requireStudent();
$student_id = $student->student_id;

try {
    $stmt = $db->prepare("
        UPDATE notifications
        SET is_read = TRUE
        WHERE student_id = :student_id AND is_read = FALSE
    ");
    $stmt->execute([':student_id' => $student_id]);
    $count = $stmt->rowCount();

    sendSuccess(200, 'All notifications marked as read.', [
        'count' => $count
    ]);

} catch (Exception $e) {
    sendError(500, 'Failed to mark notifications as read.', $e);
}
