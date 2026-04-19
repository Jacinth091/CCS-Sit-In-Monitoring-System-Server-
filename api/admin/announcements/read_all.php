<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
    $offset = ($page - 1) * $per_page;
    
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;

    $params = [];
    $where_conditions = ["deleted_at IS NULL"];

    if ($search) {
        $where_conditions[] = "(title ILIKE :search OR content ILIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($status) {
        $where_conditions[] = "status = :status";
        $params[':status'] = $status;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // Count total for pagination meta
    $count_query = "SELECT COUNT(*) FROM announcements {$where_clause}";
    $countStmt = $db->prepare($count_query);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    // Fetch data
    $main_params = $params;
    $main_params[':limit'] = $per_page;
    $main_params[':offset'] = $offset;

    $data_query = "
        SELECT id, title, content, status, is_pinned, is_important, admin_username, created_at, updated_at
        FROM announcements
        {$where_clause}
        ORDER BY is_pinned DESC, created_at DESC 
        LIMIT :limit OFFSET :offset
    ";
    $dataStmt = $db->prepare($data_query);
    $dataStmt->execute($main_params);
    
    $announcements = $dataStmt->fetchAll();

    $meta = [
        'total' => $totalRecords,
        'page' => $page,
        'per_page' => $per_page,
        'last_page' => ceil($totalRecords / $per_page)
    ];

    sendSuccess(200, 'Announcements retrieved successfully.', $announcements, $meta);

} catch (Exception $e) {
    sendError(500, 'Database error occurred while fetching announcements.', $e);
}
