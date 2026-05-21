<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

// Support both JSON and multipart/form-data
$data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
} else {
    $data = $_POST;
}

if (empty($data['id']) || empty($data['name']) || empty($data['version'])) {
    sendError(400, "ID, Name and Version are required.");
}

try {
    $swModel = new Software($db);

    if ($swModel->checkDuplicate($data['name'], $data['version'], $data['id'])) {
        sendError(409, "Software with this name and version already exists.");
    }

    $iconPath = $data['icon_path'] ?? null;

    // Handle file upload if present
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] !== UPLOAD_ERR_NO_FILE) {
        // We need the current path to delete the old file
        $currentSoftware = $db->prepare("SELECT icon_path FROM software WHERE id = :id");
        $currentSoftware->execute([':id' => $data['id']]);
        $oldPath = $currentSoftware->fetchColumn();

        $upload = ImageUploadHelper::upload($_FILES['icon'], 'icon', $oldPath);
        if (!$upload['success']) {
            sendError(400, $upload['message']);
        }
        $iconPath = $upload['path'];
    }

    $success = $swModel->update($data['id'], $data['name'], $data['version'], $data['description'] ?? null, $iconPath, $data['is_active'] ?? true);    
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
