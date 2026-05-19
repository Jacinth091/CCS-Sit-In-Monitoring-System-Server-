<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['request_ids']) || !is_array($data['request_ids'])) {
    sendError(400, 'Valid request_ids array is required.');
}

try {
    $reqModel = new SoftwareRequest($db);
    $success = $reqModel->bulkUpdateStatus($data['request_ids'], 'reviewed');
    
    if ($success) {
        $auditLog = new AuditLog($db);
        $auditLog->write('software_request', $userData->student_id, 'admin', "Marked " . count($data['request_ids']) . " requests as reviewed", 'software_request', null, null, 'update');
        
        sendSuccess(200, "Requests marked as reviewed");
    } else {
        sendError(500, "Failed to update requests.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
