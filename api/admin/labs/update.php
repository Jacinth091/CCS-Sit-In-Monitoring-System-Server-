<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id']) || empty($data['name'])) {
    sendError(400, 'Laboratory ID and name are required.');
}

try {
    $labModel = new Laboratory($db);
    $success = $labModel->update(
        $data['id'],
        $data['name'], 
        $data['lab_code'] ?? null, 
        $data['capacity'] ?? 30, 
        $data['is_active'] ?? true
    );
    
    if ($success) {
        $auditLog = new AuditLog($db);
        $auditLog->write('lab_management', $userData->student_id, 'admin', "Updated laboratory: " . $data['name'], 'laboratory', $data['id'], $data['id'], 'update');
        
        sendSuccess(200, "Laboratory updated successfully");
    } else {
        sendError(500, "Failed to update laboratory.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
