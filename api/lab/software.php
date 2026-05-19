<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$lab_id = $_GET['lab_id'] ?? null;

if (!$lab_id) {
    sendError(400, "Lab ID is required.");
}

try {
    $software = new Software($db);
    $data = $software->getByLab($lab_id);

    sendSuccess(200, "Software for lab $lab_id retrieved successfully.", $data);

} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
