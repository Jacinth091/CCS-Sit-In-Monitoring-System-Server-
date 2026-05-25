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
    
    // Fetch student IDs before updating so we can notify them
    $placeholders = implode(',', array_fill(0, count($data['request_ids']), '?'));
    $stmt = $db->prepare("SELECT id, student_id, software_name FROM student_software_requests WHERE id IN ($placeholders)");
    $stmt->execute($data['request_ids']);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $success = $reqModel->bulkUpdateStatus($data['request_ids'], 'reviewed');
    
    if ($success) {
        $auditLog = new AuditLog($db);
        $auditLog->write('software_request', $userData->student_id, 'admin', "Marked " . count($data['request_ids']) . " requests as reviewed", 'software_request', null, null, 'update');
        
        // Invalidate cache
        require_once __DIR__ . '/../../../src/helpers/AiCache.php';
        AiCache::invalidate($db, 'software_demand');

        // Notify each student
        foreach ($requests as $req) {
            create_notification(
                $db,
                $req['student_id'],
                $userData->student_id,
                'software',
                'Software Request Reviewed',
                "Your request for '{$req['software_name']}' has been reviewed by an admin.",
                $req['id'],
                'software_request'
            );
        }

        sendSuccess(200, "Requests marked as reviewed");
    } else {
        sendError(500, "Failed to update requests.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
