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
    $purpose = isset($_GET['purpose']) ? $_GET['purpose'] : null;
    $date = isset($_GET['date']) ? $_GET['date'] : null;

    // Base query
    $query = "
        FROM sit_in_logs sl
        LEFT JOIN students s ON sl.student_id = s.student_id
        LEFT JOIN laboratories l ON sl.lab_id = l.id
        LEFT JOIN admin_feedback af ON af.sit_in_id = sl.id
        LEFT JOIN feedback f ON f.sit_in_id = sl.id
        WHERE sl.deleted_at IS NULL
        AND (:search::text IS NULL OR s.student_id ILIKE :search OR CONCAT(s.first_name, ' ', s.last_name) ILIKE :search OR sl.purpose ILIKE :search)
        AND (:status::text IS NULL OR sl.status = :status)
        AND (:lab_id::text IS NULL OR sl.lab_id::text = :lab_id)
        AND (:purpose::text IS NULL OR sl.purpose = :purpose)
        AND (:date::text IS NULL OR sl.time_in::date = :date::date)
    ";

    // Count total for pagination meta
    $countStmt = $db->prepare("SELECT COUNT(*) " . $query);
    $countStmt->bindValue(':search', $search);
    $countStmt->bindValue(':status', $status);
    $countStmt->bindValue(':lab_id', $lab_id);
    $countStmt->bindValue(':purpose', $purpose);
    $countStmt->bindValue(':date', $date);
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetchColumn();

    // Fetch data
    $dataStmt = $db->prepare("
        SELECT 
            sl.id, 
            sl.id AS log_id,
            sl.purpose, 
            sl.time_in, 
            sl.time_out, 
            sl.status, 
            sl.student_id,
            s.first_name, 
            s.last_name, 
            s.middle_name,
            s.course,
            s.course_level,
            s.profile_pic,
            l.lab_name, 
            af.feedback_text AS admin_remark,
            af.id AS admin_feedback_id,
            f.rating AS student_rating,
            f.comment AS student_comment
        " . $query . "
        ORDER BY sl.time_in DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    $dataStmt->bindValue(':search', $search);
    $dataStmt->bindValue(':status', $status);
    $dataStmt->bindValue(':lab_id', $lab_id);
    $dataStmt->bindValue(':purpose', $purpose);
    $dataStmt->bindValue(':date', $date);
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
