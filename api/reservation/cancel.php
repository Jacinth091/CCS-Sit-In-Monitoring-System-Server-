<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$student = requireAuth();
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id'])) {
    sendError(400, "Reservation ID is required.");
}

try {
    $resModel = new Reservation($db);
    $auditLog = new AuditLog($db);

    // Get details for logging
    $stmt = $db->prepare("SELECT lab_id, pc_number FROM reservations WHERE id = :id");
    $stmt->execute([':id' => $data['id']]);
    $res = $stmt->fetch();

    if ($resModel->cancelByStudent($data['id'], $student->student_id)) {
        
        $action = "Reservation rejected by student";
        $description = "Reservation #{$data['id']} (PC #{$res['pc_number']}) was rejected by student {$student->student_id}";

        $auditLog->write(
            'Reservation cancelled',
            $student->student_id,
            'student',
            $description,
            'reservation',
            (string)$data['id'],
            $res['lab_id'] ?? null,
            $action
        );

        sendSuccess(200, "Reservation rejected.");
    } else {
        sendError(400, "Unable to reject reservation. It may already be rejected or doesn't belong to you.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
