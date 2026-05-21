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

if (empty($data['id']) || empty($data['name'])) {
    sendError(400, 'Laboratory ID and name are required.');
}

try {
    $imagePath = $data['image_path'] ?? null;

    // Handle file upload if present
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // We need the current path to delete the old file
        $currentLab = $db->prepare("SELECT image_path FROM laboratories WHERE id = :id");
        $currentLab->execute([':id' => $data['id']]);
        $oldPath = $currentLab->fetchColumn();

        $upload = ImageUploadHelper::upload($_FILES['image'], 'lab_image', $oldPath);
        if (!$upload['success']) {
            sendError(400, $upload['message']);
        }
        $imagePath = $upload['path'];
    }

    $labModel = new Laboratory($db);
    $success = $labModel->update(
        $data['id'],
        $data['name'], 
        $data['lab_code'] ?? null, 
        $data['capacity'] ?? 30, 
        $data['is_active'] ?? true,
        $imagePath
    );
    
    if ($success) {
        $auditLog = new AuditLog($db);
        $auditLog->write('lab_management', $userData->student_id, 'admin', "Updated laboratory: " . $data['name'], 'laboratory', $data['id'], $data['id'], 'update');
        
        sendSuccess(200, "Laboratory updated successfully");
    } else {
        sendError(500, "Failed to update laboratory.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
