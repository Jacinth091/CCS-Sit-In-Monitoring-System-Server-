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
    $auditLog = new AuditLog($db);
    $status = $data->status ?? 'published';
    $is_pinned = $data->is_pinned ?? false;
    $is_important = $data->is_important ?? false;

    $stmt = $db->prepare("
        INSERT INTO announcements (title, content, status, is_pinned, is_important, admin_username) 
        VALUES (:title, :content, :status, :is_pinned, :is_important, :admin)
        RETURNING *
    ");
    
    $stmt->execute([
        ':title' => Validator::sanitizeString($data->title),
        ':content' => $data->content, // Content might have HTML if using a rich text editor later
        ':status' => $status,
        ':is_pinned' => $is_pinned ? 1 : 0,
        ':is_important' => $is_important ? 1 : 0,
        ':admin' => $admin->student_id
    ]);

    $announcement = $stmt->fetch();

    // Log to unified audit log
    $auditLog->write(
        'Announcement created',
        $admin->student_id,
        'admin',
        "Admin created announcement: \"{$announcement['title']}\"",
        'announcement',
        $announcement['id'],
        null,
        "Created announcement \"{$announcement['title']}\""
    );

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
