<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
    $offset = ($page - 1) * $per_page;
    
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $is_read = isset($_GET['is_read']) ? ($_GET['is_read'] === 'true') : null;

    $query = "
        FROM notifications
        WHERE admin_username = :admin
        AND (:type IS NULL OR type = :type)
        AND (:is_read IS NULL OR is_read = :is_read)
    ";

    $countStmt = $db->prepare("SELECT COUNT(*) " . $query);
    $countStmt->bindValue(':admin', $admin->student_id);
    $countStmt->bindValue(':type', $type);
    $countStmt->bindValue(':is_read', $is_read, PDO::PARAM_BOOL);
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetchColumn();

    $dataStmt = $db->prepare("
        SELECT id, type, title, message, is_read, reference_id, reference_type, created_at
        " . $query . "
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    $dataStmt->bindValue(':admin', $admin->student_id);
    $dataStmt->bindValue(':type', $type);
    $dataStmt->bindValue(':is_read', $is_read, PDO::PARAM_BOOL);
    $dataStmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    
    $notifications = $dataStmt->fetchAll();

    $meta = [
        'total' => $totalRecords,
        'page' => $page,
        'per_page' => $per_page,
        'last_page' => ceil($totalRecords / $per_page)
    ];

    sendSuccess(200, 'Notifications retrieved successfully.', [
        'notifications' => $notifications,
        'meta' => $meta
    ]);

} catch (Exception $e) {
    sendError(500, 'Database error occurred while fetching notifications.', $e);
}
