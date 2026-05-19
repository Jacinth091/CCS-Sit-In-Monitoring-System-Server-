<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$student = requireAuth();
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['content']) || empty($data['rating'])) {
    sendError(400, "Content and Rating are required.");
}

$is_anonymous = isset($data['is_anonymous']) ? (bool)$data['is_anonymous'] : true;

try {
    $testModel = new Testimonial($db);
    if ($testModel->create($student->student_id, $data['content'], $data['rating'], $is_anonymous)) {
        sendSuccess(201, "Testimonial submitted for review.");
    } else {
        sendError(500, "Failed to submit testimonial.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
