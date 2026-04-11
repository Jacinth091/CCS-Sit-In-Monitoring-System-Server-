<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

if (!isset($_GET['id'])) {
    sendError(400, 'Notification ID is required.');
}

$id = (int)$_GET['id'];

try {
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :id AND admin_username = :admin");
    $stmt->execute([':id' => $id, ':admin' => $admin->student_id]);

    if ($stmt->rowCount() === 0) {
        sendError(404, 'Notification not found.');
    }

    sendSuccess(200, 'Notification marked as read.');
} catch (Exception $e) {
    sendError(500, 'Database error.', $e);
}
