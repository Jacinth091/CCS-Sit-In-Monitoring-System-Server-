<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$admin = requireAdmin();
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['lab_id']) || empty($data['software_id'])) {
    sendError(400, "Lab ID and Software ID are required.");
}

try {
    $swModel = new Software($db);
    $auditLog = new AuditLog($db);

    // Get software and lab details for logging
    $stmt = $db->prepare("SELECT name FROM software WHERE id = :id");
    $stmt->execute([':id' => $data['software_id']]);
    $sw = $stmt->fetch();

    $stmt = $db->prepare("SELECT name FROM laboratories WHERE id = :id");
    $stmt->execute([':id' => $data['lab_id']]);
    $lab = $stmt->fetch();

    if ($swModel->assignToLab($data['lab_id'], $data['software_id'])) {
        $swName = $sw['name'] ?? 'Unknown';
        $labName = $lab['name'] ?? 'Unknown';

        // Log to unified audit log
        $auditLog->write(
            'Software assigned',
            $admin->student_id,
            'admin',
            "Admin assigned software \"$swName\" to $labName",
            'software',
            (string)$data['software_id'],
            $data['lab_id'],
            "Assigned \"$swName\" to $labName"
        );

        sendSuccess(200, "Software assigned to laboratory.");
    } else {
        sendError(500, "Failed to assign software.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
