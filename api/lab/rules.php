<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

try {
    $ruleModel = new LabRule($db);
    $data = $ruleModel->getAll(false);
    sendSuccess(200, "Laboratory rules retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
