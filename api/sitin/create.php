<?php

require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

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

    // Insert sit-in log
    $stmt = $db->prepare("
        INSERT INTO sit_in_logs (student_id, lab_id, purpose, time_in, status)
        VALUES (:student_id, :lab_id, :purpose, CURRENT_TIMESTAMP, 'ongoing')
        RETURNING id
    ");
    $stmt->execute([
        ':student_id' => $data->student_id,
        ':lab_id'     => $data->lab_id,
        ':purpose'    => $data->purpose,
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