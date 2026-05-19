<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$student = requireAuth();
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id']) || empty($data['reserved_date']) || empty($data['time_slot'])) {
    sendError(400, "Reservation ID, date, and time slot are required.");
}

try {
    $resModel = new Reservation($db);
    $auditLog = new AuditLog($db);

    // Get original reservation details for validation and ownership check
    $stmt = $db->prepare("
        SELECT r.lab_id, r.pc_number, r.student_id, r.status
        FROM reservations r
        WHERE r.id = :id AND r.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $data['id']]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        sendError(404, "Reservation not found.");
    }

    // Check ownership
    if ($original['student_id'] !== $student->student_id) {
        sendError(403, "You can only reschedule your own reservations.");
    }

    // Only allow rescheduling if it's pending, approved, or already rescheduled
    if (!in_array($original['status'], ['pending', 'approved', 'rescheduled'])) {
        sendError(400, "Only pending, approved, or rescheduled reservations can be rescheduled.");
    }

    $pc_number = $data['pc_number'] ?? $original['pc_number'];
    $new_date = $data['reserved_date'];
    $new_time_slot = $data['time_slot'];

    // Validate no conflict on new slot
    $stmtConflict = $db->prepare("
        SELECT r.id FROM reservations r
        WHERE r.lab_id = :lab_id 
        AND r.pc_number = :pc_number 
        AND r.reserved_date = :date 
        AND r.status IN ('pending', 'approved', 'rescheduled')
        AND r.id != :current_id
        AND r.deleted_at IS NULL
        AND NOT EXISTS (
            SELECT 1 FROM sit_in_logs sl
            WHERE sl.lab_id = r.lab_id
            AND CAST(sl.pc_number AS text) = CAST(r.pc_number AS text)
            AND sl.student_id = r.student_id
            AND sl.status = 'completed'
            AND sl.time_out IS NOT NULL
            AND DATE(sl.time_in) = r.reserved_date
            AND sl.deleted_at IS NULL
        )
    ");
    $stmtConflict->execute([
        ':lab_id' => $original['lab_id'],
        ':pc_number' => $pc_number,
        ':date' => $new_date,
        ':current_id' => $data['id']
    ]);
    
    if ($stmtConflict->fetch()) {
        sendError(409, "The selected PC is already reserved for this date and time.");
    }

    // Update reservation
    $query = "UPDATE reservations SET 
                reserved_date = :new_date, 
                reserved_time = :new_time, 
                pc_number = :pc_number,
                status = 'pending',
                updated_at = NOW() 
              WHERE id = :id";
    
    $stmtUpdate = $db->prepare($query);
    $success = $stmtUpdate->execute([
        ':new_date' => $new_date,
        ':new_time' => $new_time_slot,
        ':pc_number' => $pc_number,
        ':id' => $data['id']
    ]);

    if ($success) {
        $action = "Reservation rescheduled by student";
        $description = "Reservation #{$data['id']} was rescheduled by student {$student->student_id} to {$new_date} {$new_time_slot}";

        $auditLog->write(
            'Reservation rescheduled',
            $student->student_id,
            'student',
            $description,
            'reservation',
            (string)$data['id'],
            $original['lab_id'],
            $action
        );

        sendSuccess(200, "Reservation rescheduled successfully. It is now pending for approval.");
    } else {
        sendError(500, "Failed to reschedule reservation.");
    }

} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
