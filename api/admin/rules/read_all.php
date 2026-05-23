<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

try {
    $ruleModel = new LabRule($db);
    $data = $ruleModel->getAll(true); // Include inactive
    sendSuccess(200, "All laboratory rules retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
