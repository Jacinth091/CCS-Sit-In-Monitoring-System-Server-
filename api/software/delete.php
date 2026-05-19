<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$admin = requireAdmin();
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id'])) {
    sendError(400, "Software ID is required.");
}

try {
    $swModel = new Software($db);
    $auditLog = new AuditLog($db);

    // Get software details for logging
    $stmt = $db->prepare("SELECT name FROM software WHERE id = :id");
    $stmt->execute([':id' => $data['id']]);
    $sw = $stmt->fetch();

    if ($swModel->delete($data['id'])) {
        $name = $sw['name'] ?? 'Unknown';
        // Log to unified audit log
        $auditLog->write(
            'Software deleted',
            $admin->student_id,
            'admin',
            "Admin deleted software: \"$name\"",
            'software',
            (string)$data['id'],
            null,
            "Deleted software \"$name\""
        );

        sendSuccess(200, "Software deleted.");
    } else {
        sendError(500, "Failed to delete software.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
