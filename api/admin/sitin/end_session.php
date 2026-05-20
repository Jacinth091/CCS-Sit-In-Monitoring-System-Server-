<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

$data = json_decode(file_get_contents("php://input"));

if (empty($data->log_id)) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing log ID.']);
    exit();
}

try {
    // Fetch log
    $stmt = $db->prepare("
        SELECT sl.status, sl.student_id, l.name
        FROM sit_in_logs sl
        LEFT JOIN laboratories l ON sl.lab_id = l.id
        WHERE sl.id = :log_id 
        LIMIT 1
    ");
    $stmt->execute([':log_id' => $data->log_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        throw new Exception('Sit-in record not found.');
    }
    if ($log['status'] === 'completed') {
        throw new Exception('This session has already been completed.');
    }

    $db->beginTransaction();

    // End the session
    $stmt = $db->prepare("
        UPDATE sit_in_logs 
        SET status = 'completed', time_out = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
        WHERE id = :log_id
        RETURNING time_out, reservation_id
    ");
    $stmt->execute([':log_id' => $data->log_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Update reservation status to fulfilled
    if (!empty($result['reservation_id'])) {
        $updRes = $db->prepare("UPDATE reservations SET status = 'fulfilled', updated_at = NOW() WHERE id = :res_id");
        $updRes->execute([':res_id' => $result['reservation_id']]);
    }

    // Deduct student session count
    $stmt = $db->prepare("
        UPDATE students 
        SET session = session - 1, updated_at = CURRENT_TIMESTAMP
        WHERE student_id = :student_id
    ");
    $stmt->execute([':student_id' => $log['student_id']]);

    // Notify student
    $time = date('H:i', strtotime($result['time_out']));
    create_notification(
        $db,
        $log['student_id'],
        $admin->student_id,
        'sit_in',
        'Session Ended by Admin',
        "Your sit-in session in {$log['name']} was ended by an admin at {$time}.",
        $data->log_id,
        'sit_in_log'
    );

    $db->commit();

    http_response_code(200);
    echo json_encode(['message' => 'Session ended successfully.']);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(400);
    echo json_encode(['message' => $e->getMessage()]);
}
