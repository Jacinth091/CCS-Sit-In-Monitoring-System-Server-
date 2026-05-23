<?php

require_once __DIR__ . '/../../includes/cors.php'; 
require_once __DIR__ . '/../../includes/initialize.php';

$student = new Student($db);

$data = json_decode(file_get_contents("php://input"));
try {
    if(        
        empty($data->student_id) ||
        empty($data->first_name) ||
        empty($data->last_name) ||
        empty($data->email) ||
        empty($data->password)
    )
    {
        sendError(400, 'Missing required registration fields.');
    }

    $errors = [];

    // Field-specific validation
    if (!Validator::isValidStudentId($data->student_id)) {
        $errors['student_id'] = 'Student ID must be exactly 8 digits.';
    }
    if (!Validator::isValidName($data->first_name)) {
        $errors['first_name'] = 'First name contains invalid characters.';
    }
    if (!Validator::isValidName($data->last_name)) {
        $errors['last_name'] = 'Last name contains invalid characters.';
    }
    if (!empty($data->middle_name) && !Validator::isValidName($data->middle_name)) {
        $errors['middle_name'] = 'Middle name contains invalid characters.';
    }
    if (!Validator::validateEmail($data->email)) {
        $errors['email'] = 'Invalid email address format.';
    }
    if (!empty($data->address) && !Validator::isValidAddress($data->address)) {
        $errors['address'] = "Invalid address format.";
    }

    if (!empty($errors)) {
        sendValidationError($errors);
    }

    $student->student_id = $data->student_id;
    $student->first_name = $data->first_name;
    $student->last_name = $data->last_name;
    $student->middle_name = $data->middle_name ?? null; 
    $student->course = $data->course;
    $student->course_level = $data->course_level;
    $student->email = $data->email;
    $student->address = $data->address;

    // Uniqueness checks
    if($student->studentIdExist($student->student_id)){
        sendValidationError(['student_id' => 'Student ID already registered.']);
    }

    if($student->emailExist()){
        sendValidationError(['email' => 'Email address already registered.']);
    }

    $student->password = password_hash($data->password, PASSWORD_DEFAULT);

    $id = $student->create();
    if ($id) {
        sendSuccess(201, 'Student registered successfully!', ['id' => $id]);
        exit();
    } else {
        sendError(503, 'Unable to register student.');
    }

} catch (PDOException $e) {
    if ($e->getCode() == 23505) {
        sendError(409, 'This record already exists.', $e);
    } else {
        sendError(500, 'A database error occurred while registering.', $e);
    }
} catch (Exception $e) {
    sendError(500, 'An unexpected server error occurred.', $e);
}
?>ent_id)",
            'student',
            (string)$data->student_id,
            null,
            "Created student $data->student_id"
        );

        sendSuccess(201, 'Student created successfully!', ['id' => $id]);
        exit();
    } else {
        sendError(503, 'Unable to create student.');
    }

} catch (PDOException $e) {
    if ($e->getCode() == 23505) {
        sendError(409, 'This record already exists.', $e);
    } else {
        sendError(500, 'A database error occurred while creating student.', $e);
    }
} catch (Exception $e) {
    sendError(500, 'An unexpected server error occurred.', $e);
}
?>