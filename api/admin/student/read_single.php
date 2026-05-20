<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$student_id = $_GET['id'] ?? null;

if (!$student_id) {
    sendError(400, "Student ID is required.");
}

try {
    $student = new Student($db);
    // Use the custom student_id field for lookup
    $details = $student->getDetailsByStudentId($student_id);

    if ($details) {
        sendSuccess(200, "Student details retrieved successfully.", $details);
    } else {
        sendError(404, "Student not found.");
    }
} catch (Exception $e) {
    sendError(500, "Error retrieving student details.", $e);
}
?>
