<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

$student = requireAuth();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['lab_id']) || empty($data['reserved_date']) || empty($data['time_slot']) || empty($data['purpose'])) {
    sendError(400, "Missing required fields.");
}

try {
    // Check if the student has remaining sessions
    $stmt = $db->prepare("SELECT session FROM students WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $student->student_id]);
    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$studentData || (int)$studentData['session'] <= 0) {
        sendError(403, "You do not have any remaining sessions. Please contact the administrator to reset your sessions.");
    }

    $resModel = new Reservation($db);

    if (!$resModel->isReservationsEnabled()) {
        sendError(403, "Reservations are currently disabled.");
    }

    // Check if the student already has an ongoing sit-in session
    $sitInModel = new SitIn($db);
    $sitInModel->student_id = $student->student_id;
    if ($sitInModel->getOngoingSession()) {
        sendError(409, "You cannot make a reservation while you have an ongoing sit-in session.");
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
