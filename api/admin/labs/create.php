<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['name'])) {
    sendError(400, 'Laboratory name is required.');
}

try {
    $labModel = new Laboratory($db);
    $labId = $labModel->create(
        $data['name'], 
        $data['lab_code'] ?? null, 
        $data['capacity'] ?? 30, 
        $data['is_active'] ?? true
    );
    
    if ($labId) {
        $auditLog = new AuditLog($db);
        $auditLog->write('lab_management', $userData->student_id, 'admin', "Created laboratory: " . $data['name'], 'laboratory', $labId, $labId, 'create');
        
        sendSuccess(201, "Laboratory created successfully", ['id' => $labId]);
    } else {
        sendError(500, "Failed to create laboratory.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
