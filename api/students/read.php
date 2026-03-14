<?php 

    headers('Access-Control-Allow-Origin: *');
    headers('Content-Type: application/json');


    require_once '../../includes/initialize.php';

    //Initialize the Students Model

    $student = new Student($db);

    $result = $student->read();
    $num = $result->rowCount();

    if($num > 0 ){
        $students_arr = array();
        while($row= $result->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $stud_item = array(
                'student_id' => $student_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'course_level' => $course_level
            );
            array_push($students_arr, $student_item);
        }
        http_response_code(200);
        echo json_encode($students_arr);
    } else {
        http_response_code(404);
        echo json_encode(array('message' => 'No students found.'));
    }
?>
