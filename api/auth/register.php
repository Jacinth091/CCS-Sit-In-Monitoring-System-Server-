<?php

require_once __DIR__ . '/../../includes/cors.php'; 
require_once __DIR__ . '/../../includes/initialize.php';

$student = new Student($db);

$data = json_decode(file_get_contents("php://input"));
try {
    if(        
        empty($data->student_id) &&
        empty($data->first_name) &&
        empty($data->last_name) &&
        empty($data->email) &&
        empty($data->address) &&
        empty($data->password)
    )
    {
        sendError(400, 'Credentials are required.');
    }




    $student->student_id = $data->student_id;
    $student->first_name = $data->first_name;
    $student->last_name = $data->last_name;
    $student->middle_name = $data->middle_name ?? null; 
    $student->course = $data->course;
    $student->course_level = $data->course_level;
    $student->email = $data->email;
    $student->address = $data->address;

    if($student->emailExist()){
        sendError(409, 'Email already exist!');
    }

    $student->password = password_hash($data->password, PASSWORD_DEFAULT);

    if ($student->create()) {
        sendSuccess(201, 'Student registered successfully!');
        exit();
    } else {
        sendError(503, 'Unable to register student.');
    }

} catch (PDOException $e) {
    if ($e->getCode() == 23505) {
        sendError(409, 'This email address is already registered.', $e);
    } else {
        sendError(500, 'A database error occurred while registering.', $e);
    }
} catch (Exception $e) {
    sendError(500, 'An unexpected server error occurred.', $e);
}
?>
