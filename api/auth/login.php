<?php 
require_once __DIR__ . '/../../includes/cors.php'; 
require_once __DIR__ . '/../../includes/initialize.php';
require_once __DIR__ . '/../../src/middleware/AiAuthMiddleware.php';

use Firebase\JWT\JWT;

$data = json_decode(file_get_contents("php://input"));

if (empty($data->student_id) || empty($data->password)) {
    sendError(400, 'Student ID/Username and password are required.');
}

try {
    $secretKey = $_ENV['JWT_SECRET'] ?? 'default_secret_key_change_me'; 
    $issuedAt  = time();

    // 1. Check if it's an Admin login
    $envAdminUsername = $_ENV['ADMIN_USERNAME'] ?? 'admin';
    $envAdminPassword = $_ENV['ADMIN_PASSWORD'] ?? 'admin123';

    if ($data->student_id === $envAdminUsername && $data->password === $envAdminPassword) {
        $expireAdmin = $issuedAt + (10 * 60 * 60); // 10 hours

        $payload = [
            'iat'  => $issuedAt,
            'exp'  => $expireAdmin,
            'data' => [
                'id'         => 999, // Changed from 0 to avoid 'falsy' issues
                'role'       => 'admin',
                'student_id' => $envAdminUsername,
                'first_name' => $_ENV['ADMIN_FIRST_NAME'] ?? 'System',
                'last_name'  => $_ENV['ADMIN_LAST_NAME']  ?? 'Administrator'
            ]
        ];

        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        // Register session in user_sessions
        $tokenHash   = hash('sha256', $jwt);
        $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        $expiresAt   = date('Y-m-d H:i:s', $payload['exp']);
        $clientIp    = AiAuthMiddleware::getClientIp();

        $stmtSession = $db->prepare(
            "INSERT INTO user_sessions (user_id, role, token_hash, device_fingerprint, ip_address, expires_at)
             VALUES (?, ?, ?, ?, ?::inet, ?)"
        );
        $stmtSession->execute([
            $payload['data']['id'], $payload['data']['role'], $tokenHash, $fingerprint,
            $clientIp, $expiresAt
        ]);

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

    // Register session in user_sessions
    $tokenHash   = hash('sha256', $jwt);
    $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    $expiresAt   = date('Y-m-d H:i:s', $payload['exp']);
    $clientIp    = AiAuthMiddleware::getClientIp();

    $stmtSession = $db->prepare(
        "INSERT INTO user_sessions (user_id, role, token_hash, device_fingerprint, ip_address, expires_at)
         VALUES (?, ?, ?, ?, ?::inet, ?)"
    );
    $stmtSession->execute([
        $payload['data']['id'], $payload['data']['role'], $tokenHash, $fingerprint,
        $clientIp, $expiresAt
    ]);

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
