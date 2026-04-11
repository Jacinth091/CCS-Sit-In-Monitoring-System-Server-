<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();
$data = json_decode(file_get_contents("php://input"));

if (!isset($_GET['id'])) {
    sendError(400, 'Announcement ID is required.');
}

$id = (int)$_GET['id'];

$errors = [];
if (empty($data->title)) $errors['title'] = 'Title is required.';
if (empty($data->content)) $errors['content'] = 'Content is required.';

if (!empty($errors)) {
    sendValidationError($errors);
}

try {
    $stmt = $db->prepare("
        UPDATE announcements 
        SET title = :title, 
            content = :content, 
            status = :status, 
            is_pinned = :is_pinned, 
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND deleted_at IS NULL
        RETURNING *
    ");
    
    $stmt->execute([
        ':title' => Validator::sanitizeString($data->title),
        ':content' => $data->content,
        ':status' => $data->status ?? 'published',
        ':is_pinned' => ($data->is_pinned ?? false) ? 1 : 0,
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
