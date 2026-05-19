<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$admin = requireAdmin();
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['lab_id']) || empty($data['name']) || empty($data['version'])) {
    sendError(400, "Lab ID, Name, and Version are required.");
}

try {
    $swModel = new Software($db);
    $auditLog = new AuditLog($db);

    $id = $swModel->create($data['lab_id'] ?? null, $data['name'], $data['version'], $data['description'] ?? null);
    if ($id) {
        // Log to unified audit log
        $auditLog->write(
            'Software created',
            $admin->student_id,
            'admin',
            "Admin created software: \"{$data['name']}\" (v{$data['version']})",
            'software',
            (string)$id,
            $data['lab_id'] ?? null,
            "Created software \"{$data['name']}\""
        );

        sendSuccess(201, "Software created.", ['id' => $id]);
    } else {
        sendError(500, "Failed to create software.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
