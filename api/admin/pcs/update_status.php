<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

// The spec says { pc_id, status }, but we might need lab_id/pc_number if no record exists yet
$pc_id = $data['pc_id'] ?? null;
$lab_id = $data['lab_id'] ?? null;
$pc_number = $data['pc_number'] ?? null;
$status = $data['status'] ?? null; // active, disabled, or under maintenance

if (!$status || (!$pc_id && (!$lab_id || !$pc_number))) {
    sendError(400, "Missing required fields (status and either pc_id or lab_id+pc_number).");
}

// Map short-form status to DB-valid status if needed
if ($status === 'maintenance') $status = 'under maintenance';

try {
    $pcModel = new PC($db);
    $auditLog = new AuditLog($db);
    
    // Get PC details for logging
    if ($pc_id) {
        $stmt = $db->prepare("
            SELECT p.pc_number, p.lab_id, l.name 
            FROM pcs p 
            JOIN laboratories l ON p.lab_id = l.id 
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $pc_id]);
        $pc = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT name FROM laboratories WHERE id = :id");
        $stmt->execute([':id' => $lab_id]);
        $lab = $stmt->fetch(PDO::FETCH_ASSOC);
        $pc = ['pc_number' => $pc_number, 'lab_id' => $lab_id, 'name' => $lab['name'] ?? 'Unknown Lab'];
    }

    if ($pc_id) {
        $success = $pcModel->updateStatus($pc_id, $status);
    } else {
        $stmt = $db->prepare("SELECT reservation_status FROM pcs WHERE lab_id = :lab_id AND pc_number = :pc_number");
        $stmt->execute([':lab_id' => $lab_id, ':pc_number' => $pc_number]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $existingReservation = $existing['reservation_status'] ?? null;
        $res_status = $existingReservation;

        if ($status === 'disabled' || $status === 'under maintenance') {
            $res_status = 'unavailable';
        } elseif ($status === 'active') {
            if (!in_array($existingReservation, ['reserved', 'occupied'], true)) {
                $res_status = 'open';
            }
        }

        if (!$res_status) {
            $res_status = ($status === 'active') ? 'open' : 'unavailable';
        }

        $upsert_id = $pcModel->upsert($lab_id, $pc_number, $status, $res_status);
        $pc_id = $upsert_id;
        $success = (bool)$upsert_id;
    }

    if ($success) {
        $labName = $pc['name'] ?? 'Unknown Lab';
        $pcNum = $pc['pc_number'] ?? '?';
        $statusLabel = ucfirst($status);

        $action = "Functional State updated to $statusLabel";
        $description = "$labName's (PC #$pcNum) Functional State was updated to $statusLabel";

        // Log to unified audit log
        $auditLog->write(
            'PC status changed',
            $admin->student_id ?? 'admin',
            'admin',
            $description,
            'pc',
            $pc_id,
            $pc['lab_id'] ?? $lab_id,
            $action
        );
        sendSuccess(200, "PC operational status updated to '$status'.");
    } else {
        sendError(500, "Failed to update PC status.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
