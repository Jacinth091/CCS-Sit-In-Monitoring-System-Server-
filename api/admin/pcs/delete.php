<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id'])) {
    sendError(400, "PC ID is required.");
}

try {
    $pcModel = new PC($db);
    $success = $pcModel->delete($data['id']);
    
    if ($success) {
        $auditLog = new AuditLog($db);
        $auditLog->write('pc_management', $userData->student_id, 'admin', "Deleted PC ID: " . $data['id'], 'pc', $data['id'], null, 'delete');
        
        sendSuccess(200, "PC deleted successfully.");
    } else {
        sendError(500, "Failed to delete PC.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
