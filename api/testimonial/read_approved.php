<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

try {
    $testModel = new Testimonial($db);
    $data = $testModel->read(false, null, null, null, null, true);
    sendSuccess(200, "Approved testimonials retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
