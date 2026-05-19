<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$currentUser = requireAdmin();

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id)) {
    http_response_code(400);
    echo json_encode(['message' => 'Student ID is required.']);
    exit();
}

try {
    $auditLog = new AuditLog($db);
    $db->beginTransaction();

    // Get student details for logging
    $stmt = $db->prepare("SELECT student_id, first_name, last_name FROM students WHERE id = :id");
    $stmt->execute([':id' => $data->id]);
    $student = $stmt->fetch();

    // Perform a soft delete by marking inactive and recording the timestamp
    $stmt = $db->prepare('UPDATE students SET is_active = FALSE, deleted_at = CURRENT_TIMESTAMP WHERE id = :id');
    if ($stmt->execute([':id' => $data->id])) {
        
        $name = ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '');
        $sid = $student['student_id'] ?? 'Unknown';

        // Log to unified audit log
        $auditLog->write(
            'Student deactivated',
            $currentUser->student_id,
            'admin',
            "Admin deactivated student account: $name ($sid)",
            'student',
            (string)$sid,
            null,
            "Deactivated student $sid"
        );

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
