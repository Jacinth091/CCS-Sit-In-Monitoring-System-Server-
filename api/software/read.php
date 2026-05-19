<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

requireAuth();

$lab_id = $_GET['lab_id'] ?? null;

try {
    $swModel = new Software($db);
    if ($lab_id) {
        $data = $swModel->getByLab($lab_id);
    } else {
        // Return all unique software or something sensible for 'getAll'
        $data = $swModel->getAllGroupedByLab(); // Or a flat list if preferred
    }
    sendSuccess(200, "Software retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
