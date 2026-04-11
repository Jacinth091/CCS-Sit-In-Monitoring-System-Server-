<?php
// includes/validate_token.php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * Validates the incoming JWT and returns the user payload.
 * If the token is missing, invalid, or expired, it kills the script.
 */
function authenticate() {
    $authHeader = null;

    // 1. Safely grab the Authorization header (handles different server setups like Apache/Nginx)
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

    // 2. If there's no header at all, kick them out
    if (!$authHeader) {
        sendError(401, 'Access denied. No token provided.');
    }

    // 3. Extract the actual token string from "Bearer eyJhbG..."
    $parts = explode(" ", $authHeader);
    if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
        sendError(401, 'Access denied. Invalid token format.');
    }

    $jwt = $parts[1];

    // 4. Decode and Verify
    try {
        $secretKey = $_ENV['JWT_SECRET'] ?? 'default_secret_key_change_me';
        
        // Note: php-jwt requires the Key object for security
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
        
        // Return the 'data' array we packed into the payload during login
        return $decoded->data; 

    } catch (ExpiredException $e) {
        // Specific error for expired tokens (triggers your Axios 401 logout)
        sendError(401, 'Session expired. Please log in again.');
    } catch (Exception $e) {
        // Catch tampered or malformed tokens
        sendError(401, 'Access denied. Token is invalid.');
    }
}

/**
 * Requires the caller to be an authenticated ADMIN.
 * Returns the user payload, or kills the request with 403.
 */
function requireAdmin() {
    $user = authenticate();
    if ($user->role !== 'admin') {
        sendError(403, 'Access denied. Admins only.');
    }
    return $user;
}

/**
 * Requires the caller to be an authenticated STUDENT.
 * Returns the user payload, or kills the request with 403.
 */
function requireStudent() {
    $user = authenticate();
    if ($user->role !== 'student') {
        sendError(403, 'Access denied. Students only.');
    }
    return $user;
}

/**
 * Requires any authenticated user (student or admin).
 * Alias for authenticate() — makes intent explicit at the call site.
 */
function requireAuth() {
    return authenticate();
}

/**
 * Requires the user to have one of the allowed roles.
 */
function requireRole($allowedRoles = []) {
    $user = authenticate();
    if (!in_array($user->role, $allowedRoles)) {
        sendError(403, 'Access denied. Unauthorized role.');
    }
    return $user;
}