<?php 

 
    require_once '../../includes/cors.php'; 
    require_once '../../includes/initialize.php';

    $student = new Student($db);

    $data = json_decode(file_get_contents("php://input"));

    // Validate input
    if (empty($data->student_id) || empty($data->password)) {
        http_response_code(400);
        echo json_encode(array('message' => 'Student ID and password are required.'));
        exit();
    }

    $student->student_id = $data->student_id;

    $row = $student->login();

    if (!$row) {
        http_response_code(404);
        echo json_encode(array('message' => 'Student not found.'));
        exit();
    }

    // Check if account is active
    if (!$row['is_active']) {
        http_response_code(403);
        echo json_encode(array('message' => 'Account is deactivated.'));
        exit();
    }

    // Verify password
    if (!password_verify($data->password, $row['password'])) {
        http_response_code(401);
        echo json_encode(array('message' => 'Invalid credentials.'));
        exit();
    }

    // Success
    http_response_code(200);
    echo json_encode(array(
        'message'    => 'Login successful.',
        'student_id' => $row['student_id'],
        'first_name' => $row['first_name'],
        'last_name'  => $row['last_name'],
        'email'      => $row['email']
    ));

?>