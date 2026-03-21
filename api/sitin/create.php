<?php
// api/sitin/create.php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$data = json_decode(file_get_contents("php://input"));

if(empty($data->student_id) || empty($data->lab_id) || empty($data->purpose)) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing required fields.']);
    exit();
}

$student_id = $data->student_id;
$lab_id = $data->lab_id;
$purpose = $data->purpose;

try {
    $db->beginTransaction();

    // Check if student exists and has sessions left
    $stmt = $db->prepare("SELECT id, session, is_active FROM students WHERE student_id = :student_id LIMIT 1");
    $stmt->execute([':student_id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found.');
    }
    if (!$student['is_active']) {
        throw new Exception('Student account is deactivated.');
    }
    if ($student['session'] <= 0) {
        throw new Exception('Student has zero remaining sessions.');
    }

    // Check if already sitting in
    $stmt = $db->prepare("SELECT id FROM sit_in_logs WHERE student_id = :student_id AND status = 'Active' LIMIT 1");
    $stmt->execute([':student_id' => $student_id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Student is already in an active sit-in session.');
    }

    // Insert sit-in log
    $stmt = $db->prepare("INSERT INTO sit_in_logs (student_id, lab_id, purpose, time_in, status) VALUES (:student_id, :lab_id, :purpose, CURRENT_TIMESTAMP, 'Active')");
    $stmt->execute([
        ':student_id' => $data->student_id,
        ':lab_id' => $data->lab_id,
        ':purpose' => $data->purpose
    ]);

    $db->commit();
    http_response_code(201);
    echo json_encode(['message' => 'Sit-in session created successfully.']);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode(['message' => $e->getMessage()]);
}
