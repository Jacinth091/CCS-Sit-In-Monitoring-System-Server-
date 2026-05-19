<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['lab_id']) || empty($data['pc_number'])) {
    sendError(400, "Lab ID and PC Number are required.");
}

try {
    $pcModel = new PC($db);
    $id = $pcModel->create($data['lab_id'], $data['pc_number'], $data['pc_status'] ?? 'active', $data['reservation_status'] ?? 'open', $data['notes'] ?? null);
    
    if ($id) {
        $auditLog = new AuditLog($db);
        $auditLog->write('pc_management', $userData->student_id, 'admin', "Created PC " . $data['pc_number'], 'pc', $id, $data['lab_id'], 'create');
        
        sendSuccess(201, "PC added successfully.", ['id' => $id]);
    } else {
        sendError(500, "Failed to create PC.");
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'duplicate key') !== false || strpos($e->getMessage(), 'unique constraint') !== false) {
        sendError(400, "PC number already exists in this lab.");
    } else {
        sendError(500, "An error occurred.", $e);
    }
}
