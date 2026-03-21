<?php
// api/sitin/end_session.php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$data = json_decode(file_get_contents("php://input"));

if(empty($data->log_id)) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing log ID.']);
    exit();
}

$log_id = $data->log_id;

try {
    // Check if it's already completed
    $stmt = $db->prepare("SELECT status, student_id FROM sit_in_logs WHERE id = :log_id LIMIT 1");
    $stmt->execute([':log_id' => $log_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        throw new Exception("Sit-in record not found.");
    }
    if ($log['status'] === 'Completed') {
        throw new Exception("This session has already been completed.");
    }

    $db->beginTransaction();

    $stmt = $db->prepare("UPDATE sit_in_logs SET status = 'Completed', time_out = CURRENT_TIMESTAMP WHERE id = :log_id");
    $stmt->execute([':log_id' => $log_id]);

    // Deduct the student's session count
    $stmt = $db->prepare("UPDATE students SET session = session - 1 WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $log['student_id']]);

    $db->commit();

    http_response_code(200);
    echo json_encode(['message' => 'Session explicitly ended.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['message' => $e->getMessage()]);
}
