<?php
/**
 * DELETE /api/student/notifications/delete_all.php
 * Purpose: Delete all of the student's notifications.
 */

require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendError(405, 'Method not allowed. Use DELETE.');
}

$student = requireStudent();
$student_id = $student->student_id;

try {
    $stmt = $db->prepare("
        DELETE FROM notifications
        WHERE student_id = :student_id
    ");
    $stmt->execute([':student_id' => $student_id]);
    $count = $stmt->rowCount();

    sendSuccess(200, 'All notifications cleared.', [
        'count' => $count
    ]);

} catch (Exception $e) {
    sendError(500, 'Failed to clear notifications.', $e);
}
