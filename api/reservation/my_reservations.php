<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$student = requireAuth();

try {
    $resModel = new Reservation($db);
    $data = $resModel->getByStudent($student->student_id);
    sendSuccess(200, "Reservations retrieved.", $data);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
