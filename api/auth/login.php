<?php 
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

use Firebase\JWT\JWT;

$data = json_decode(file_get_contents("php://input"));

if (empty($data->student_id) || empty($data->password)) {
    sendError(400, 'Student ID/Username and password are required.');
}

try {
    $secretKey = $_ENV['JWT_SECRET']; 
    $issuedAt  = time();

    // 1. Check if it's an Admin login
    $admin = new Admin($db);
    $admin->username = $data->student_id;
    $adminRow = $admin->login();

    if ($adminRow && password_verify($data->password, $adminRow['password'])) {
        $expireAdmin = $issuedAt + (10 * 60 * 60); // 10 hours

        $payload = [
            'iat'  => $issuedAt,
            'exp'  => $expireAdmin,
            'data' => [
                'id'         => $adminRow['id'],
                'role'       => 'admin',
                'student_id' => $adminRow['username'],
                'first_name' => $adminRow['first_name'],
                'last_name'  => $adminRow['last_name']
            ]
        ];

        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        sendSuccess(200, 'Admin login successful.', [
            'token' => $jwt,
            'user'  => $payload['data']
        ]);
    }

    // 2. Fallback to Student login
    $student = new Student($db);
    $student->student_id = $data->student_id;
    $row = $student->login();

    if (!$row) {
        sendError(404, 'User not found.');
    }

    if (!$row['is_active']) {
        sendError(403, 'Account is deactivated.');
    }

    if (!password_verify($data->password, $row['password'])) {
        sendError(401, 'Invalid credentials.');
    }

    $expireStudent = $issuedAt + (1 * 60 * 60); // Student gets 1 hour

    $payload = [
        'iat'  => $issuedAt,
        'exp'  => $expireStudent,
        'data' => [
            'id'         => $row['id'],
            'student_id' => $row['student_id'],
            'role'       => 'student'
        ]
    ];

    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    $userData = [
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
    ];

    sendSuccess(200, 'Login successful.', [
        'token' => $jwt,
        'user'  => $userData
    ]);

} catch (Exception $ex) {
    sendError(500, 'An unexpected server error occurred during login.', $ex);
}