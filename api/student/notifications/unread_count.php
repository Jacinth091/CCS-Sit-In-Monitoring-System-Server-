<?php
/**
 * GET /api/student/notifications/unread_count.php
 * Purpose: Return the count of unread notifications for the student.
 */

require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$student = requireStudent();
$student_id = $student->student_id;

try {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS unread_count
        FROM notifications
        WHERE student_id = :student_id AND is_read = FALSE
    ");
    $stmt->execute([':student_id' => $student_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    sendSuccess(200, 'Unread count fetched successfully.', [
        'unread_count' => (int)$result['unread_count']
    ]);

} catch (Exception $e) {
    sendError(500, 'Failed to fetch unread count.', $e);
}
