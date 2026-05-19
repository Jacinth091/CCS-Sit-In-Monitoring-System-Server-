<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['name']) || empty($data['version'])) {
    sendError(400, "Name and Version are required.");
}

try {
    $swModel = new Software($db);

    if ($swModel->checkDuplicate($data['name'], $data['version'])) {
        sendError(409, "Software with this name and version already exists.");
    }

    $id = $swModel->create($data['name'], $data['version'], $data['description'] ?? null, $data['icon_path'] ?? null, $data['is_active'] ?? true);
    
    if ($id) {
        if (!empty($data['lab_ids']) && is_array($data['lab_ids'])) {
            $swModel->syncLabs($id, $data['lab_ids']);
        }
        
        $auditLog = new AuditLog($db);
        $auditLog->write('software_management', $userData->student_id, 'admin', "Created software: " . $data['name'], 'software', $id, null, 'create');
        
        sendSuccess(201, "Software created.", ['id' => $id]);
    } else {
        sendError(500, "Failed to create software.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
