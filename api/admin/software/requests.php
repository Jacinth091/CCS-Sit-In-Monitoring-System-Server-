<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

try {
    $reqModel = new SoftwareRequest($db);
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $requests = $reqModel->getAll($status);
    $summary = $reqModel->getSummary();
    
    sendSuccess(200, "Requests retrieved", ['requests' => $requests, 'summary' => $summary]);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
