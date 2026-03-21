<?php 

require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

$data = json_decode(file_get_contents("php://input"));

if (empty($data->student_id) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(array('message' => 'Student ID and password are required.'));
    exit();
}

if ($data->student_id === ADMIN_USERNAME && $data->password === ADMIN_PASSWORD) {
    http_response_code(200);
    echo json_encode(array(
        'message'    => 'Login successful.',
        'role'       => 'admin',
        'student_id' => 'admin',
        'first_name' => 'Admin',
        'last_name'  => '',
        'email'      => ''
    ));
    exit();
}

$student = new Student($db);
$student->student_id = $data->student_id;

$row = $student->login();

if (!$row) {
    http_response_code(404);
    echo json_encode(array('message' => 'Student not found.'));
    exit();
}

if (!$row['is_active']) {
    http_response_code(403);
    echo json_encode(array('message' => 'Account is deactivated.'));
    exit();
}


if (!password_verify($data->password, $row['password'])) {
    http_response_code(401);
    echo json_encode(array('message' => 'Invalid credentials.'));
    exit();
}

// Success - student login
http_response_code(200);
echo json_encode(array(
    'message'      => 'Login successful.',
    'role'         => 'student',
    'id'           => $row['id'],
    'student_id'   => $row['student_id'],
    'first_name'   => $row['first_name'],
    'last_name'    => $row['last_name'],
    'middle_name'  => $row['middle_name'] ?? '',
    'email'        => $row['email'],
    'course'       => $row['course'] ?? '',
    'course_level' => $row['course_level'] ?? '',
    'address'      => $row['address'] ?? '',
    'session'      => $row['session'] ?? 0,
    'profile_pic'  => $row['profile_pic'] ?? null
));