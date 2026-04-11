<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$currentUser = requireAdmin();

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id)) {
    http_response_code(400);
    echo json_encode(['message' => 'Student ID is required.']);
    exit();
}

try {
    $db->beginTransaction();

    // Perform a soft delete by marking inactive and recording the timestamp
    $stmt = $db->prepare('UPDATE students SET is_active = FALSE, deleted_at = CURRENT_TIMESTAMP WHERE id = :id');
    if ($stmt->execute([':id' => $data->id])) {
        $db->commit();
        http_response_code(200);
        echo json_encode(['message' => 'Student successfully deactivated.']);
    } else {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['message' => 'Failed to deactivate student.']);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
}
