<?php 
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

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
        http_response_code(200);
        echo json_encode($students_arr);
    } else {
        http_response_code(404);
        echo json_encode(array('message' => 'No students found.'));
    }
?>
