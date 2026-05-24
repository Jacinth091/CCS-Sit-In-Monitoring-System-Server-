<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';

// Auth: Admins only
$currentUser = requireAdmin();

$settingsFile = '../../config/ai_settings.json';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Return current configuration state
    $currentStatus = AI_ENABLED;
    sendSuccess(200, 'AI settings retrieved successfully', [
        'ai_enabled' => $currentStatus
    ]);
} elseif ($method === 'POST') {
    // Update setting state
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($input['ai_enabled'])) {
        sendError(400, 'Missing ai_enabled parameter in request body.');
    }
    
    $newValue = (bool) $input['ai_enabled'];
    
    // Save to local JSON file
    $settings = [
        'ai_enabled' => $newValue,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $currentUser->username ?? 'Admin'
    ];
    
    if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT)) === false) {
        sendError(500, 'Failed to save settings changes to disk.');
    }
    
    sendSuccess(200, 'AI systems status updated successfully', [
        'ai_enabled' => $newValue
    ]);
} else {
    sendError(405, 'HTTP Method Not Allowed');
}
