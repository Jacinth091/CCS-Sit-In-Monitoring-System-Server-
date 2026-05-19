<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

if (!isset($_GET['id'])) {
    sendError(400, 'Announcement ID is required.');
}

$id = (int)$_GET['id'];

try {
    $auditLog = new AuditLog($db);

    // Get title for logging
    $stmt = $db->prepare("SELECT title FROM announcements WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $announcement = $stmt->fetch();

    $stmt = $db->prepare("UPDATE announcements SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        sendError(404, 'Announcement not found or already deleted.');
    }

    // Log to unified audit log
    $title = $announcement['title'] ?? 'Unknown';
    $auditLog->write(
        'Announcement deleted',
        $admin->student_id,
        'admin',
        "Admin deleted announcement #$id: \"$title\"",
        'announcement',
        (string)$id,
        null,
        "Deleted announcement \"$title\""
    );

    sendSuccess(200, 'Announcement deleted successfully.');
} catch (Exception $e) {
    sendError(500, 'Database error occurred while deleting announcement.', $e);
}
