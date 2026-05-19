<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id'])) {
    sendError(400, 'Software ID is required.');
}

try {
    $swModel = new Software($db);
    $success = $swModel->delete($data['id']);
    
    if ($success) {
        $auditLog = new AuditLog($db);
        $auditLog->write('software_management', $userData->student_id, 'admin', "Deleted software ID: " . $data['id'], 'software', $data['id'], null, 'delete');
        
        sendSuccess(200, "Software deleted successfully");
    } else {
        sendError(500, "Failed to delete software.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
