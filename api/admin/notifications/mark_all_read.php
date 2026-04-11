<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

try {
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE admin_username = :admin AND is_read = FALSE");
    $stmt->execute([':admin' => $admin->student_id]);

    sendSuccess(200, 'All notifications marked as read.', ['affected_rows' => $stmt->rowCount()]);
} catch (Exception $e) {
    sendError(500, 'Database error.', $e);
}
