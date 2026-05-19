<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$student = requireAuth();

try {
    $sitinModel = new SitIn($db);
    $data = $sitinModel->getStudentSummary($student->student_id);
    sendSuccess(200, "Sit-in summary retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
