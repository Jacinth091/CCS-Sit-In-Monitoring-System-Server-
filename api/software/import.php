<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$admin = requireAdmin();
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['lab_id']) || empty($data['items'])) {
    sendError(400, "Lab ID and Items array are required.");
}

try {
    $swModel = new Software($db);
    $auditLog = new AuditLog($db);

    $count = $swModel->bulkInsert($data['lab_id'], $data['items']);
    
    if ($count !== false) {
        // Log to unified audit log
        $auditLog->write(
            'Software imported',
            $admin->student_id,
            'admin',
            "Admin performed bulk software import ($count items) for Lab #{$data['lab_id']}",
            'software',
            'bulk_import',
            $data['lab_id'],
            "Imported $count software items"
        );

        sendSuccess(200, "Imported $count software items.");
    } else {
        sendError(500, "Import failed.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
