<?php 
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$currentUser = requireAdmin();

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : null;
    $per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;

    $student = new Student($db);

    if ($page === null) {
        $result = $student->read(null, null, $search);
        $num = $result->rowCount();

        if ($num > 0) {
            $students_arr = array();
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                array_push($students_arr, $row);
            }
            sendSuccess(200, 'Students retrieved successfully.', $students_arr);
        } else {
            sendError(404, 'No students found.');
        }
    } else {
        $res = $student->read($page, $per_page, $search);
        $result = $res['stmt'];
        $totalRecords = $res['total'];
        
        $students_arr = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            array_push($students_arr, $row);
        }

        sendSuccess(200, 'Students retrieved successfully.', [
            'records' => $students_arr,
            'meta' => [
                'total' => $totalRecords,
                'page' => $page,
                'per_page' => $per_page,
                'last_page' => ceil($totalRecords / $per_page)
            ]
        ]);
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
?>
