<?php
// api/sitin/read_by_student.php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

if(empty($_GET['student_id'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing student ID.']);
    exit();
}

$student_id = $_GET['student_id'];

$query = 'SELECT s.*, l.lab_name 
          FROM sit_in_logs s
          LEFT JOIN laboratories l ON s.lab_id = l.id
          WHERE s.student_id = :student_id 
          ORDER BY s.time_in DESC';

$stmt = $db->prepare($query);
$stmt->execute([':student_id' => $student_id]);

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode($logs);
