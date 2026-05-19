<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

$pc_id = $data['pc_id'] ?? null;
$lab_id = $data['lab_id'] ?? null;
$pc_number = $data['pc_number'] ?? null;
$reservation_status = $data['reservation_status'] ?? null; // open, reserved, or occupied

if (!$reservation_status || (!$pc_id && (!$lab_id || !$pc_number))) {
    sendError(400, "Missing required fields (reservation_status and either pc_id or lab_id+pc_number).");
}

try {
    $pcModel = new PC($db);
    $auditLog = new AuditLog($db);

    // 1. Fetch current status and details for logging
    if ($pc_id) {
        $stmt = $db->prepare("
            SELECT p.pc_status, p.pc_number, p.lab_id, l.name 
            FROM pcs p 
            JOIN laboratories l ON p.lab_id = l.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $pc_id]);
    } else {
        $stmt = $db->prepare("
            SELECT p.pc_status, p.id as pc_id, l.name 
            FROM pcs p 
            JOIN laboratories l ON p.lab_id = l.id
            WHERE p.lab_id = :lab_id AND p.pc_number = :pc_number
        ");
        $stmt->execute([':lab_id' => $lab_id, ':pc_number' => $pc_number]);
    }
    
    $pc = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_pc_status = $pc['pc_status'] ?? 'active';
    $log_pc_num = $pc['pc_number'] ?? $pc_number;
    $log_lab_id = $pc['lab_id'] ?? $lab_id;
    $labName = $pc['name'] ?? 'Unknown Lab';

    // 2. Reject if pc_status is not active
    if ($current_pc_status !== 'active') {
        sendError(403, "Cannot change reservation status when PC is $current_pc_status. It must be 'active' first.");
    }

    // 3. Update
    if ($pc_id) {
        $success = $pcModel->updateReservationStatus($pc_id, $reservation_status);
    } else {
        $upsert_id = $pcModel->upsert($lab_id, $pc_number, 'active', $reservation_status);
        $pc_id = $upsert_id;
        $success = (bool)$upsert_id;
    }

    if ($success) {
        $statusLabel = ucfirst($reservation_status);
        $action = "Reservation Status set to $statusLabel";
        $description = "$labName's (PC #$log_pc_num) Reservation Status was updated to $statusLabel";

        // Log to unified audit log
        $auditLog->write(
            'PC reservation status changed',
            $admin->student_id ?? 'admin',
            'admin',
            $description,
            'pc',
            $pc_id,
            $log_lab_id,
            $action
        );
        sendSuccess(200, "PC reservation status updated to '$reservation_status'.");
    } else {
        sendError(500, "Failed to update PC reservation status.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
