<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

// Parse authorization header
$authHeader = null;
if (isset($_SERVER['Authorization'])) {
    $authHeader = trim($_SERVER["Authorization"]);
} else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { 
    $authHeader = trim($_SERVER["HTTP_AUTHORIZATION"]);
} elseif (function_exists('apache_request_headers')) {
    $requestHeaders = apache_request_headers();
    if (isset($requestHeaders['Authorization'])) {
        $authHeader = trim($requestHeaders['Authorization']);
    }
}

if ($authHeader) {
    $parts = explode(" ", $authHeader);
    if (count($parts) === 2 && $parts[0] === 'Bearer') {
        $jwt = $parts[1];
        $tokenHash = hash('sha256', $jwt);
        
        // Invalidate session
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = FALSE WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);
    }
}

sendSuccess(200, 'Logout successful.');
