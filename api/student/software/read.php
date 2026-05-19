<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireStudent();

try {
    $swModel = new Software($db);
    $software = $swModel->getAll();
    
    // For students, we might only want to send active software, but getAll() fetches all. 
    // We can filter here or just send all and let UI handle.
    $activeSoftware = array_filter($software, function($s) {
        return $s['is_active'];
    });
    
    sendSuccess(200, "Software retrieved successfully", array_values($activeSoftware));
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
