<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$student = requireAuth();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['lab_id']) || empty($data['reserved_date']) || empty($data['time_slot']) || empty($data['purpose'])) {
    sendError(400, "Missing required fields.");
}

try {
    $resModel = new Reservation($db);

    if (!$resModel->isReservationsEnabled()) {
        sendError(403, "Reservations are currently disabled.");
    }

    $pc_number = !empty($data['pc_number']) ? $data['pc_number'] : null;

    if ($pc_number && $resModel->isSlotTaken($data['lab_id'], $pc_number, $data['reserved_date'], $data['time_slot'])) {
        sendError(409, "This slot is already taken.");
    }

    $id = $resModel->create([
        'student_id'    => $student->student_id,
        'lab_id'        => $data['lab_id'],
        'pc_number'     => $pc_number,
        'reserved_date' => $data['reserved_date'],
        'time_slot'     => $data['time_slot'],
        'purpose'       => $data['purpose']
    ]);

    if ($id) {
        sendSuccess(201, "Reservation booked successfully.", ['id' => $id]);
    } else {
        sendError(500, "Failed to book reservation.");
    }

} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
