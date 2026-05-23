<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : null;
    $per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 10;
    
    $search = isset($_GET['search']) ? '%' . trim($_GET['search']) . '%' : null;
    $purpose = isset($_GET['purpose']) ? trim($_GET['purpose']) : null;

    $baseQuery = "
        FROM sit_in_logs sl
        INNER JOIN students     s ON sl.student_id = s.student_id
        INNER JOIN laboratories l ON sl.lab_id     = l.id
        WHERE sl.status     = 'ongoing'
        AND   sl.deleted_at IS NULL
    ";

    $params = [];

    if ($search !== null) {
        $baseQuery .= " AND (s.student_id ILIKE :search OR CONCAT(s.first_name, ' ', s.last_name) ILIKE :search OR l.name ILIKE :search OR l.lab_code ILIKE :search OR sl.pc_number::text ILIKE :search)";
        $params[':search'] = $search;
    }

    if ($purpose !== null && $purpose !== 'all') {
        if ($purpose === 'Other') {
            $predefined = ["C Programming", "Java Programming", "Web Development", "Database Design", "Object Oriented Programming", "Networking", "System Architecture", "Mobile App Development"];
            $predefined_placeholders = [];
            foreach ($predefined as $i => $val) {
                $key = ':pre_' . $i;
                $predefined_placeholders[] = $key;
                $params[$key] = $val;
            }
            $baseQuery .= " AND sl.purpose NOT IN (" . implode(", ", $predefined_placeholders) . ")";
        } else {
            $baseQuery .= " AND sl.purpose = :purpose";
            $params[':purpose'] = $purpose;
        }
    }

    if ($page === null) {
        // Unpaginated query
        $stmt = $db->prepare("
            SELECT
                sl.id,
                sl.id           AS log_id,
                sl.purpose,
                sl.time_in,
                sl.status,
                s.student_id,
                s.first_name,
                s.last_name,
                s.profile_pic,
                s.course,
                s.course_level,
                s.session,
                l.name,
                l.lab_code,
                sl.pc_number
            " . $baseQuery . "
            ORDER BY sl.time_in DESC
        ");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($logs);
    } else {
        // Paginated query
        $offset = ($page - 1) * $per_page;

        // Count total
        $countStmt = $db->prepare("SELECT COUNT(*) " . $baseQuery);
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalRecords = (int)$countStmt->fetchColumn();

        // Count system-wide total ongoing
        $totalActiveStmt = $db->prepare("SELECT COUNT(*) FROM sit_in_logs WHERE status = 'ongoing' AND deleted_at IS NULL");
        $totalActiveStmt->execute();
        $totalActiveNow = (int)$totalActiveStmt->fetchColumn();

        // Fetch paginated
        $stmt = $db->prepare("
            SELECT
                sl.id,
                sl.id           AS log_id,
                sl.purpose,
                sl.time_in,
                sl.status,
                s.student_id,
                s.first_name,
                s.last_name,
                s.profile_pic,
                s.course,
                s.course_level,
                s.session,
                l.name,
                l.lab_code,
                sl.pc_number
            " . $baseQuery . "
            ORDER BY sl.time_in DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meta = [
            'total' => $totalRecords,
            'page' => $page,
            'per_page' => $per_page,
            'last_page' => ceil($totalRecords / $per_page),
            'total_active_now' => $totalActiveNow
        ];

        sendSuccess(200, 'Active sessions retrieved successfully.', [
            'records' => $logs,
            'meta' => $meta
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to fetch active sessions.', 'details' => $e->getMessage()]);
}

