<?php
/**
 * GET /api/student/announcements/read_single.php
 * Purpose: Return a single published announcement by ID.
 */

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$student = requireStudent();

if (empty($_GET['id'])) {
    sendError(400, 'Announcement ID is required.');
}

$id = (int)$_GET['id'];

try {
    $query = "
        SELECT
            id,
            title,
            content AS body,
            is_pinned,
            is_important,
            created_at,
            updated_at,
            admin_username AS author_name
        FROM announcements
        WHERE id = :id AND status = 'published' AND deleted_at IS NULL;
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$announcement) {
        sendError(404, 'Announcement not found.');
    }

    sendSuccess(200, 'Announcement fetched successfully.', $announcement);

} catch (Exception $e) {
    sendError(500, 'Failed to fetch announcement.', $e);
}
