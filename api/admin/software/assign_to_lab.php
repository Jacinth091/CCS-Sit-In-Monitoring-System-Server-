<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['lab_id']) || empty($data['software_id'])) {
    sendError(400, "Lab ID and Software ID are required.");
}

try {
    $swModel = new Software($db);
    $success = $swModel->assignToLab($data['lab_id'], $data['software_id']);
    
    if ($success) {
        $auditLog = new AuditLog($db);
        $auditLog->write('software_management', $userData->student_id, 'admin', "Assigned Software ID: {$data['software_id']} to Lab ID: {$data['lab_id']}", 'software', $data['software_id'], $data['lab_id'], 'assign');
        
        sendSuccess(200, "Software assigned successfully.");
    } else {
        // Maybe it's already assigned
        sendError(400, "Failed to assign software or already assigned.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
