<?php
/**
 * GET /api/student/sitin/read.php
 * Purpose: Return sit-in records, paginated and date-filterable.
 * - Students can only view their own records.
 * - Admins can view any student's records by providing a `student_id` query param.
 */

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$user = requireAuth(); // Require any authenticated user

$student_id = null;

// Role-based access control
if ($user->role === 'admin') {
    if (empty($_GET['student_id'])) {
        sendError(400, 'Bad Request: student_id is required for admin users.');
    }
    $student_id = $_GET['student_id'];
} else if ($user->role === 'student') {
    // If a student tries to access another student's records, deny it.
    if (!empty($_GET['student_id']) && $_GET['student_id'] != $user->student_id) {
        sendError(403, 'Access Denied: You can only view your own records.');
    }
    $student_id = $user->student_id;
} else {
    // Should not happen if requireAuth() is working, but as a safeguard.
    sendError(403, 'Access Denied: Unauthorized role.');
}


// Pagination params
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 50) : 20;
$offset = ($page - 1) * $per_page;

// Filters
$date_from = !empty($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = !empty($_GET['date_to']) ? $_GET['date_to'] : null;
$name = !empty($_GET['name']) ? $_GET['name'] : null;

try {
    $params = [':student_id' => $student_id];
    
    $where_conditions = [
        "sl.student_id = :student_id",
        "sl.deleted_at IS NULL"
    ];

    if ($date_from) {
        $where_conditions[] = "sl.time_in::date >= :date_from::date";
        $params[':date_from'] = $date_from;
    }
    if ($date_to) {
        $where_conditions[] = "sl.time_in::date <= :date_to::date";
        $params[':date_to'] = $date_to;
    }
    if ($name) {
        $where_conditions[] = "l.name = :name";
        $params[':name'] = $name;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // 1. Get the records
    $query = "
        SELECT
            sl.id,
            sl.id AS log_id,
            sl.purpose,
            l.name,
            l.lab_code,
            sl.pc_number,
            sl.time_in,
            sl.time_out,
            sl.status,
            CASE
                WHEN sl.time_out IS NOT NULL THEN
                    ROUND(EXTRACT(EPOCH FROM (sl.time_out - sl.time_in)) / 60)
                ELSE NULL
            END AS duration_minutes,
            f.rating AS student_rating,
            f.comment AS student_comment,
            f.id AS student_feedback_id,
            af.feedback_text AS admin_remark,
            af.id AS admin_feedback_id,
            af.updated_at AS admin_feedback_date
        FROM sit_in_logs sl
        LEFT JOIN laboratories l ON sl.lab_id = l.id
        LEFT JOIN feedback f ON f.sit_in_id = sl.id
        LEFT JOIN admin_feedback af ON af.sit_in_id = sl.id
        {$where_clause}
        ORDER BY sl.time_in DESC
        LIMIT :limit OFFSET :offset;
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get total count for meta
    $count_query = "
        SELECT COUNT(sl.id) 
        FROM sit_in_logs sl
        LEFT JOIN laboratories l ON sl.lab_id = l.id
        {$where_clause};
    ";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();

    $last_page = ceil($total / $per_page);

    sendSuccess(200, 'Sit-in records fetched successfully.', $logs, [
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'last_page' => $last_page
    ]);

} catch (Exception $e) {
    sendError(500, 'Failed to fetch sit-in records.', $e);
}
