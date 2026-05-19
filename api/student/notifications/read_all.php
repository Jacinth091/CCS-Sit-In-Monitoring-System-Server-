<?php
/**
 * GET /api/student/notifications/read_all.php
 * Purpose: Return the authenticated student's personal notifications, paginated and filterable.
 */

require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$student = requireStudent();
$student_id = $student->student_id;

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 20;
    $offset = ($page - 1) * $per_page;
    
    $type = !empty($_GET['type']) ? $_GET['type'] : null;
    $is_read = isset($_GET['is_read']) ? ($_GET['is_read'] === 'true') : null;

    $whereClauses = ["student_id = :student_id"];
    $params = [':student_id' => $student_id];

    if ($type !== null) {
        $whereClauses[] = "type = :type";
        $params[':type'] = $type;
    }

    if ($is_read !== null) {
        $whereClauses[] = "is_read = :is_read";
        $params[':is_read'] = $is_read;
    }

    $whereSql = "WHERE " . implode(" AND ", $whereClauses);

    // 1. Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM notifications " . $whereSql);
    foreach ($params as $key => $val) {
        if (is_bool($val)) {
            $countStmt->bindValue($key, $val, PDO::PARAM_BOOL);
        } else {
            $countStmt->bindValue($key, $val);
        }
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetchColumn();

    // 2. Get data
    $dataStmt = $db->prepare("
        SELECT id, type, title, message, is_read, reference_id, reference_type, created_at
        FROM notifications
        $whereSql
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $val) {
        if (is_bool($val)) {
            $dataStmt->bindValue($key, $val, PDO::PARAM_BOOL);
        } else {
            $dataStmt->bindValue($key, $val);
        }
    }
    $dataStmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    
    $notifications = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    $meta = [
        'total' => $totalRecords,
        'page' => $page,
        'per_page' => $per_page,
        'last_page' => (int)ceil($totalRecords / $per_page)
    ];

    sendSuccess(200, 'Notifications retrieved successfully.', $notifications, $meta);

} catch (Exception $e) {
    sendError(500, 'Database error occurred while fetching notifications.', $e);
}
