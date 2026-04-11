<?php 
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$currentUser = requireAdmin();

    //Initialize the Students Model

    $student = new Student($db);

    $result = $student->read();
    $num = $result->rowCount();

    if($num > 0 ){
        $students_arr = array();
        while($row = $result->fetch(PDO::FETCH_ASSOC)){
            // push the entire row so frontend gets id, course, session, profile_pic, etc.
            array_push($students_arr, $row);
        }
        sendSuccess(200, 'Students retrieved successfully.', $students_arr);
        // http_response_code(200);
        // echo json_encode($students_arr);
    } else {
        // http_response_code(404);
        // echo json_encode(array('message' => 'No students found.'));
        sendError(404, 'No students found.');
    }
?>
