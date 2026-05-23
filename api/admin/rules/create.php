<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['title']) || empty($data['description'])) {
    sendError(400, "Title and description are required.");
}

try {
    $ruleModel = new LabRule($db);
    $success = $ruleModel->create(
        $data['title'],
        $data['description'],
        $data['icon_name'] ?? 'Shield',
        $data['display_order'] ?? 0,
        $data['is_active'] ?? true
    );

    if ($success) {
        sendSuccess(201, "Laboratory rule created successfully.");
    } else {
        sendError(500, "Failed to create laboratory rule.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
