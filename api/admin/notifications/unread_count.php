<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

try {
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE admin_username = :admin AND is_read = FALSE");
    $stmt->execute([':admin' => $admin->student_id]);
    $result = $stmt->fetch();
    
    sendSuccess(200, 'Unread count retrieved.', ['unread_count' => (int)$result['unread_count']]);
} catch (Exception $e) {
    sendError(500, 'Database error.', $e);
}
