<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

try {
    require_once __DIR__ . '/../../src/models/Course.php';
    
    $courseModel = new Course($db);
    $courses = $courseModel->read();

    sendSuccess(200, "Courses fetched successfully", $courses);

} catch (Exception $e) {
    sendError(500, "Failed to load courses: " . $e->getMessage());
}
