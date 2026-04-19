<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();
$data = json_decode(file_get_contents("php://input"));

if (!isset($_GET['id'])) {
    sendError(400, 'Announcement ID is required.');
}

$id = (int)$_GET['id'];

try {
    // 1. Fetch current announcement data
    $stmt = $db->prepare("SELECT * FROM announcements WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetch();

    if (!$current) {
        sendError(404, 'Announcement not found.');
    }

    // 2. Prepare update data - merge current with new
    $title = isset($data->title) ? Validator::sanitizeString($data->title) : $current['title'];
    $content = isset($data->content) ? $data->content : $current['content'];
    $status = $data->status ?? $current['status'];
    $is_pinned = isset($data->is_pinned) ? ($data->is_pinned ? 1 : 0) : ($current['is_pinned'] ? 1 : 0);
    $is_important = isset($data->is_important) ? ($data->is_important ? 1 : 0) : ($current['is_important'] ? 1 : 0);

    $stmt = $db->prepare("
        UPDATE announcements 
        SET title = :title, 
            content = :content, 
            status = :status, 
            is_pinned = :is_pinned,
            is_important = :is_important,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND deleted_at IS NULL
        RETURNING *
    ");
    
    $stmt->execute([
        ':title' => $title,
        ':content' => $content,
        ':status' => $status,
        ':is_pinned' => (int)$is_pinned,
        ':is_important' => (int)$is_important,
        ':id' => $id
    ]);

    $announcement = $stmt->fetch();

    if (!$announcement) {
        sendError(404, 'Announcement not found.');
    }

    // 4. Notify students if it was just published
    if ($announcement['status'] === 'published') {
        // You might want to check if it was already published before, 
        // but for now we follow the plan to notify on publish.
        notify_all_students(
            $db,
            'announcement',
            'Updated Announcement',
            "Announcement updated: " . $announcement['title'],
            $announcement['id'],
            'announcement',
            $admin->student_id
        );
    }

    sendSuccess(200, 'Announcement updated successfully.', $announcement);
} catch (Exception $e) {
    sendError(500, 'Database error occurred while updating announcement.', $e);
}
