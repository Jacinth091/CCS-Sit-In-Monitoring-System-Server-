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
    $id = $data['id'];
    unset($data['id']);

    if (empty($data)) {
        sendError(400, "No data provided to update.");
    }

    $success = $ruleModel->update($id, $data);

    if ($success) {
        sendSuccess(200, "Laboratory rule updated successfully.");
    } else {
        sendError(500, "Failed to update laboratory rule.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
