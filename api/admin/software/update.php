<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id']) || empty($data['name']) || empty($data['version'])) {
    sendError(400, "ID, Name and Version are required.");
}

try {
    $swModel = new Software($db);
    
    if ($swModel->checkDuplicate($data['name'], $data['version'], $data['id'])) {
        sendError(409, "Software with this name and version already exists.");
    }

    $success = $swModel->update($data['id'], $data['name'], $data['version'], $data['description'] ?? null, $data['icon_path'] ?? null, $data['is_active'] ?? true);
    
    if ($success) {
        if (isset($data['lab_ids']) && is_array($data['lab_ids'])) {
            $swModel->syncLabs($data['id'], $data['lab_ids']);
        }
        
        $auditLog = new AuditLog($db);
        $auditLog->write('software_management', $userData->student_id, 'admin', "Updated software: " . $data['name'], 'software', $data['id'], null, 'update');
        
        sendSuccess(200, "Software updated successfully");
    } else {
        sendError(500, "Failed to update software.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
