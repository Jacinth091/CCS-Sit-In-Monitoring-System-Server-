<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id']) || empty($data['status'])) {
    sendError(400, "ID and Status are required.");
}

try {
    $resModel = new Reservation($db);
    $auditLog = new AuditLog($db);

    // Get reservation details for logging
    $stmt = $db->prepare("
        SELECT r.lab_id, r.pc_number, l.name, s.first_name, s.last_name 
        FROM reservations r
        JOIN laboratories l ON r.lab_id = l.id
        JOIN students s ON r.student_id = s.student_id
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $data['id']]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resModel->updateStatus($data['id'], $data['status'], $data['admin_note'] ?? null)) {
        $labName = $res['name'] ?? 'Unknown Lab';
        $pcNum = $res['pc_number'] ?? '?';
        $studentName = ($res['first_name'] ?? '') . ' ' . ($res['last_name'] ?? '');
        $statusLabel = ucfirst($data['status']);

        $action = "Reservation updated to $statusLabel";
        $description = "Reservation for $labName's (PC #$pcNum) by $studentName was updated to $statusLabel";

        // Log to unified audit log
        $auditLog->write(
            'Reservation ' . $data['status'],
            $admin->student_id ?? 'admin',
            'admin',
            $description,
            'reservation',
            $data['id'],
            $res['lab_id'] ?? null,
            $action
        );
        sendSuccess(200, "Reservation status updated to '" . $data['status'] . "'.");
    } else {
        sendError(500, "Failed to update status.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
