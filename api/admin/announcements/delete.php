<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

if (!isset($_GET['id'])) {
    sendError(400, 'Announcement ID is required.');
}

$id = (int)$_GET['id'];

try {
    $stmt = $db->prepare("UPDATE announcements SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        sendError(404, 'Announcement not found or already deleted.');
    }

    sendSuccess(200, 'Announcement deleted successfully.');
} catch (Exception $e) {
    sendError(500, 'Database error occurred while deleting announcement.', $e);
}
