<?php
/**
 * GET /api/student/announcements/read.php
 * Purpose: Return published announcements for the student to read. Pinned items appear first.
 */

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$student = requireStudent();

// Pagination params
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 50) : 20;
$offset = ($page - 1) * $per_page;

$pinned_only = isset($_GET['pinned']) ? ($_GET['pinned'] === 'true') : null;

try {
    $params = [];
    $count_params = [];

    $query_base = "
        FROM announcements
        WHERE
            status = 'published'
            AND deleted_at IS NULL";

    if ($pinned_only !== null) {
        $query_base .= " AND is_pinned = :pinned";
        $params[':pinned'] = $pinned_only;
        $count_params[':pinned'] = $pinned_only;
    }

    $query = "
        SELECT
            id,
            title,
            content,
            status,
            is_pinned,
            is_important,
            admin_username,
            created_at,
            updated_at
        " . $query_base . "
        ORDER BY is_pinned DESC, created_at DESC
        LIMIT :limit OFFSET :offset;
    ";
    
    $params[':limit'] = $per_page;
    $params[':offset'] = $offset;

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $count_query = "SELECT COUNT(*) " . $query_base;
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($count_params);
    $total = (int)$count_stmt->fetchColumn();

    $last_page = ceil($total / $per_page);

    sendSuccess(200, 'Announcements fetched successfully.', $announcements, [
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'last_page' => $last_page
    ]);

} catch (Exception $e) {
    sendError(500, 'Failed to fetch announcements.', $e);
}
