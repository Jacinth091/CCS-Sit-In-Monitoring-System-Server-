<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$data = json_decode(file_get_contents("php://input"));

if (empty($data->student_id) || empty($data->lab_id) || empty($data->purpose)) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing required fields.']);
    exit();
}

try {
    $db->beginTransaction();

    // Check student exists, is active, and has sessions left
    $stmt = $db->prepare("
        SELECT id, session, is_active 
        FROM students 
        WHERE student_id = :student_id 
        LIMIT 1
    ");
    $stmt->execute([':student_id' => $data->student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found.');
    }
    if (!$student['is_active']) {
        throw new Exception('Student account is deactivated.');
    }
    if ($student['session'] <= 0) {
        throw new Exception('No remaining sessions left.');
    }

    // Check if already has an active session
    $stmt = $db->prepare("
        SELECT id FROM sit_in_logs 
        WHERE student_id = :student_id 
        AND status = 'ongoing' 
        LIMIT 1
    ");
    $stmt->execute([':student_id' => $data->student_id]);
    if ($stmt->fetch()) {
        throw new Exception('Student already has an active sit-in session.');
    }

    // Check if PC is provided and available
    if (!empty($data->pc_number)) {
        $sitIn = new SitIn($db);
        $resModel = new Reservation($db);

        // Check if PC is currently in use
        if ($sitIn->isPcInUse($data->lab_id, $data->pc_number)) {
            throw new Exception("PC #{$data->pc_number} is currently occupied by another student.");
        }

        // Check if PC has an approved reservation for now
        if ($resModel->isPcReservedNow($data->lab_id, $data->pc_number)) {
            // We should check if the reservation belongs to THIS student
            // For simplicity, let's just warn or block if it's someone else's.
            // A more advanced check would allow it if it's the student's own reservation.
            
            $stmtRes = $db->prepare("
                SELECT student_id FROM reservations 
                WHERE lab_id = :lab_id AND pc_number = :pc_number 
                AND reserved_date = CURRENT_DATE AND status = 'approved'
                AND reserved_time BETWEEN (CURRENT_TIME - INTERVAL '30 minutes') AND (CURRENT_TIME + INTERVAL '60 minutes')
                LIMIT 1
            ");
            $stmtRes->execute([':lab_id' => $data->lab_id, ':pc_number' => $data->pc_number]);
            $reservation = $stmtRes->fetch(PDO::FETCH_ASSOC);

            if ($reservation && $reservation['student_id'] !== $data->student_id) {
                throw new Exception("PC #{$data->pc_number} is reserved for another student at this time.");
            }
        }
    }

    // Insert sit-in log
    $stmt = $db->prepare("
        INSERT INTO sit_in_logs (student_id, lab_id, purpose, pc_number, time_in, status)
        VALUES (:student_id, :lab_id, :purpose, :pc_number, CURRENT_TIMESTAMP, 'ongoing')
        RETURNING id
    ");
    $stmt->execute([
        ':student_id' => $data->student_id,
        ':lab_id'     => $data->lab_id,
        ':purpose'    => $data->purpose,
        ':pc_number'  => $data->pc_number ?? null
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $db->commit();

    http_response_code(201);
    echo json_encode([
        'message' => 'Sit-in session started successfully.',
        'log_id'  => $row['id']
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(400);
    echo json_encode(['message' => $e->getMessage()]);
}
