<?php
require_once __DIR__ . '/../../includes/cors.php'; 
require_once __DIR__ . '/../../includes/initialize.php';

requireAdmin();

try {
    $dashboard = new Dashboard($db);
    $stats = $dashboard->getStats();

    sendSuccess(200, 'Dashboard statistics retrieved.', $stats);
} catch(Exception $e) {
    sendError(500, 'Database error occurred while fetching dashboard stats.', $e);
}
