<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['is_approved'])) {
    sendError(400, "ID and Approval status are required.");
}

try {
    $testModel = new Testimonial($db);
    if ($testModel->updateStatus($data['id'], $data['is_approved'])) {
        $status = $data['is_approved'] ? "approved" : "disapproved";
        sendSuccess(200, "Testimonial $status successfully.");
    } else {
        sendError(500, "Failed to update testimonial status.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
