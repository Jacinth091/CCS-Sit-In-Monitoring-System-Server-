<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['id'])) {
    sendError(400, "Rule ID is required.");
}

try {
    $ruleModel = new LabRule($db);
    $success = $ruleModel->delete($data['id']);

    if ($success) {
        sendSuccess(200, "Laboratory rule deleted successfully.");
    } else {
        sendError(500, "Failed to delete laboratory rule.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
