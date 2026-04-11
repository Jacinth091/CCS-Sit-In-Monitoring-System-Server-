<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();
$data = json_decode(file_get_contents("php://input"));

$errors = [];
if (empty($data->title)) $errors['title'] = 'Title is required.';
if (empty($data->content)) $errors['content'] = 'Content is required.';

if (!empty($errors)) {
    sendValidationError($errors);
}

try {
    $status = $data->status ?? 'published';
    $is_pinned = $data->is_pinned ?? false;

    $stmt = $db->prepare("
        INSERT INTO announcements (title, content, status, is_pinned, admin_username) 
        VALUES (:title, :content, :status, :is_pinned, :admin)
        RETURNING *
    ");
    
    $stmt->execute([
        ':title' => Validator::sanitizeString($data->title),
        ':content' => $data->content, // Content might have HTML if using a rich text editor later
        ':status' => $status,
        ':is_pinned' => $is_pinned ? 1 : 0,
        ':admin' => $admin->student_id
    ]);

    $announcement = $stmt->fetch();

    // 4. Notify all students if published
    if ($status === 'published') {
        notify_all_students(
            $db,
            'announcement',
            'New Announcement',
            "New announcement: " . $announcement['title'],
            $announcement['id'],
            'announcement',
            $admin->student_id
        );
    }
    
    sendSuccess(201, 'Announcement created successfully.', $announcement);
} catch (Exception $e) {
    sendError(500, 'Database error occurred while creating announcement.', $e);
}
