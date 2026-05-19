<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id'])) {
    sendError(400, 'Laboratory ID is required.');
}

try {
    $labModel = new Laboratory($db);
    $success = $labModel->delete($data['id']);
    
    if ($success) {
        $auditLog = new AuditLog($db);
        $auditLog->write('lab_management', $userData->student_id, 'admin', "Deleted laboratory ID: " . $data['id'], 'laboratory', $data['id'], $data['id'], 'delete');
        
        sendSuccess(200, "Laboratory deleted successfully");
    } else {
        sendError(500, "Failed to delete laboratory.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
