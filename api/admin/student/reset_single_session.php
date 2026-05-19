<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['student_id'])) {
        sendError(400, "Student ID is required.");
    }

    $studentId = $data['student_id'];
    $auditLog = new AuditLog($db);
    
    // Reset session to 30 for the specific student
    $stmt = $db->prepare('UPDATE students SET session = 30, updated_at = CURRENT_TIMESTAMP WHERE student_id = :student_id');
    
    if ($stmt->execute([':student_id' => $studentId])) {
        $rowCount = $stmt->rowCount();

        if ($rowCount === 0) {
            sendError(404, "Student not found or already has 30 sessions.");
        }

        // Fetch student name for logging
        $stmtName = $db->prepare('SELECT first_name, last_name FROM students WHERE student_id = :student_id');
        $stmtName->execute([':student_id' => $studentId]);
        $studentData = $stmtName->fetch(PDO::FETCH_ASSOC);
        $studentName = $studentData ? $studentData['first_name'] . ' ' . $studentData['last_name'] : $studentId;

        // Log to unified audit log
        $auditLog->write(
            'Session reset',
            $admin->student_id,
            'admin',
            "Admin reset sessions to 30 for student {$studentName} ({$studentId}).",
            'system',
            'single_reset',
            null,
            "Reset session for {$studentId}"
        );

        sendSuccess(200, "Successfully reset sessions to 30 for {$studentName}.");
    } else {
        sendError(500, "Failed to reset session. Database error.");
    }
} catch (Exception $e) {
    sendError(500, 'Error: ' . $e->getMessage());
}
