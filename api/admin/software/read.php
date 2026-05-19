<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

try {
    $swModel = new Software($db);
    $software = $swModel->getAll();
    sendSuccess(200, "Software retrieved successfully", $software);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
