<?php

require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

$student = new Student($db);

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->student_id) &&
    !empty($data->first_name) &&
    !empty($data->last_name) &&
    !empty($data->email) &&
    !empty($data->password)
) {

    $student->student_id = $data->student_id;
    $student->first_name = $data->first_name;
    $student->last_name = $data->last_name;
    $student->middle_name = $data->middle_name ?? null; 
    $student->course = $data->course;
    $student->course_level = $data->course_level;
    $student->email = $data->email;

    $student->password = password_hash($data->password, PASSWORD_DEFAULT);

    if ($student->create()) {
        http_response_code(201);
        echo json_encode(array('message' => 'Student successfully registered for the CCS laboratories.'));
    } else {
        http_response_code(503);
        echo json_encode(array('message' => 'Unable to register student.'));
    }
} else {
    http_response_code(400);
    echo json_encode(array('message' => 'Unable to register. Data is incomplete.'));
}
?>