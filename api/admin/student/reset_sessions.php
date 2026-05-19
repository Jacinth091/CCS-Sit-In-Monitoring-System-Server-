<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

try {
    $auditLog = new AuditLog($db);
    // Reset all sessions to the absolute default value (30) for active students.
    $stmt = $db->prepare('UPDATE students SET session = 30 WHERE is_active = TRUE');
    
    if ($stmt->execute()) {
        $rowCount = $stmt->rowCount();

        // Log to unified audit log
        $auditLog->write(
            'Sessions reset',
            $admin->student_id,
            'admin',
            "Admin performed bulk session reset to 30 for $rowCount active students.",
            'system',
            'bulk_reset',
            null,
            "Reset sessions for $rowCount students"
        );

        http_response_code(200);
        echo json_encode(['message' => "Successfully reset sessions to 30 for {$rowCount} active students."]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to reset sessions. Database error.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
}
