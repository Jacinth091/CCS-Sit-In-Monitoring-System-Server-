<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

requireAuth();

try {
    $swModel = new Software($db);
    $data = $swModel->getAllGroupedByLab();
    sendSuccess(200, "Software grouped by lab retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "Failed to retrieve software list.", $e);
}
