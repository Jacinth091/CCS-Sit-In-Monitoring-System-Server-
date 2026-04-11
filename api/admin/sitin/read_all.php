<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
    $offset = ($page - 1) * $per_page;
    
    $search = isset($_GET['search']) ? '%' . trim($_GET['search']) . '%' : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $lab_id = isset($_GET['lab_id']) ? $_GET['lab_id'] : null;

    // Base query
    $query = "
        FROM sit_in_logs sl
        LEFT JOIN students s ON sl.student_id = s.student_id
        LEFT JOIN laboratories l ON sl.lab_id = l.id
        LEFT JOIN admin_feedback af ON af.sit_in_id = sl.id
        WHERE sl.deleted_at IS NULL
        AND (:search IS NULL OR s.student_id ILIKE :search OR CONCAT(s.first_name, ' ', s.last_name) ILIKE :search)
        AND (:status IS NULL OR sl.status = :status)
        AND (:lab_id IS NULL OR sl.lab_id = :lab_id)
    ";

    // Count total for pagination meta
    $countStmt = $db->prepare("SELECT COUNT(*) " . $query);
    $countStmt->bindValue(':search', $search);
    $countStmt->bindValue(':status', $status);
    $countStmt->bindValue(':lab_id', $lab_id);
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetchColumn();

    // Fetch data
    $dataStmt = $db->prepare("
        SELECT 
            sl.id, 
            sl.purpose, 
            sl.time_in, 
            sl.time_out, 
            sl.status, 
            sl.student_id,
            s.first_name, 
            s.last_name, 
            l.lab_name, 
            af.feedback_text,
            af.id AS feedback_id
        " . $query . "
        ORDER BY sl.time_in DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    $dataStmt->bindValue(':search', $search);
    $dataStmt->bindValue(':status', $status);
    $dataStmt->bindValue(':lab_id', $lab_id);
    $dataStmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    
    $records = $dataStmt->fetchAll();

    $meta = [
        'total' => $totalRecords,
        'page' => $page,
        'per_page' => $per_page,
        'last_page' => ceil($totalRecords / $per_page)
    ];

    sendSuccess(200, 'Sit-in records retrieved successfully.', [
        'records' => $records,
        'meta' => $meta
    ]);

} catch (Exception $e) {
    sendError(500, 'Database error occurred while fetching sit-in records.', $e);
}
