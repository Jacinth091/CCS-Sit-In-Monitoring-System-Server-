<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireStudent();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['software_name'])) {
    sendError(400, "Software Name is required.");
}

try {
    $reqModel = new SoftwareRequest($db);
    $success = $reqModel->create(
        $userData->student_id, 
        $data['software_name'], 
        $data['reason'] ?? null, 
        $data['lab_id'] ?? null
    );
    
    if ($success) {
        sendSuccess(201, "Software request submitted successfully.");
    } else {
        sendError(500, "Failed to submit request.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
