<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

try {
    $testModel = new Testimonial($db);
    $data = $testModel->read(true);
    sendSuccess(200, "Testimonials retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
