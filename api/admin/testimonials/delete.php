<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    sendError(400, "ID is required.");
}

try {
    $testModel = new Testimonial($db);
    if ($testModel->delete($data['id'])) {
        sendSuccess(200, "Testimonial deleted successfully.");
    } else {
        sendError(500, "Failed to delete testimonial.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
