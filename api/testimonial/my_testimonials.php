<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$student = requireAuth();

try {
    $testModel = new Testimonial($db);
    $data = $testModel->getByStudent($student->student_id);
    sendSuccess(200, "Your testimonials retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
