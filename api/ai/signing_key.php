<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai_limits.php';

// Auth - validate JWT
$currentUser = requireAuth();

sendSuccess(200, 'Signing key retrieved successfully', [
    'signing_key' => HMAC_SECRET
]);
