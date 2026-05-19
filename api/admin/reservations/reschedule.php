<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['reservation_id']) || empty($data['new_date']) || empty($data['new_time_slot'])) {
    sendError(400, "Reservation ID, new date, and new time slot are required.");
}

try {
    $resModel = new Reservation($db);
    $auditLog = new AuditLog($db);

    // Get original reservation details for validation and logging
    $stmt = $db->prepare("
        SELECT r.lab_id, r.pc_number, l.name, s.first_name, s.last_name, r.student_id
        FROM reservations r
        JOIN laboratories l ON r.lab_id = l.id
        JOIN students s ON r.student_id = s.student_id
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $data['reservation_id']]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        sendError(404, "Reservation not found.");
    }

    $pc_number = $data['new_pc_number'] ?? $original['pc_number'];

    // Validate no conflict on new slot
    if ($resModel->isSlotTaken($original['lab_id'], $pc_number, $data['new_date'], $data['new_time_slot'])) {
        sendError(409, "The selected PC is already reserved for this date and time.");
    }

    // Perform reschedule
    $success = $resModel->reschedule(
        $data['reservation_id'],
        $data['new_date'],
        $data['new_time_slot'],
        $data['new_pc_number'] ?? null,
        $data['admin_note'] ?? null
    );

    if ($success) {
        $labName = $original['name'] ?? 'Unknown Lab';
        $studentName = ($original['first_name'] ?? '') . ' ' . ($original['last_name'] ?? '');
        $action = "Reservation rescheduled to {$data['new_date']} {$data['new_time_slot']}";
        $description = "Reservation for $labName's (PC #$pc_number) by $studentName was rescheduled to {$data['new_date']} {$data['new_time_slot']}";

        // Log to unified audit log
        $auditLog->write(
            'Reservation rescheduled',
            $admin->student_id,
            'admin',
            $description,
            'reservation',
            $data['reservation_id'],
            $original['lab_id'],
            $action
        );

        // Notify Student
        create_notification(
            $db,
            $original['student_id'],
            $admin->student_id,
            'reservation',
            'Reservation Rescheduled',
            "Your reservation for $labName (PC #$pc_number) has been rescheduled to {$data['new_date']} at {$data['new_time_slot']}.",
            $data['reservation_id'],
            'reservation'
        );

        sendSuccess(200, "Reservation rescheduled successfully.");
    } else {
        sendError(500, "Failed to reschedule reservation.");
    }

} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
