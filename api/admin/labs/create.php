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

if (empty($data['name'])) {
    sendError(400, 'Laboratory name is required.');
}

try {
    $imagePath = $data['image_path'] ?? null;

    // Handle file upload if present
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = ImageUploadHelper::upload($_FILES['image'], 'lab_image');
        if (!$upload['success']) {
            sendError(400, $upload['message']);
        }
        $imagePath = $upload['path'];
    }

    $labModel = new Laboratory($db);
    $labId = $labModel->create(
        $data['name'], 
        $data['lab_code'] ?? null, 
        $data['capacity'] ?? 30, 
        $data['is_active'] ?? true,
        $imagePath
    );
    
    if ($labId) {
        $auditLog = new AuditLog($db);
        $auditLog->write('lab_management', $userData->student_id, 'admin', "Created laboratory: " . $data['name'], 'laboratory', $labId, $labId, 'create');
        
        sendSuccess(201, "Laboratory created successfully", ['id' => $labId]);
    } else {
        sendError(500, "Failed to create laboratory.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
