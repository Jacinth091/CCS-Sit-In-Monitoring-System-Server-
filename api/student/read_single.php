<?php
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

$student = new Student($db);

// Expect id from query string: /read_single.php?id=<uuid>
$student->id = isset($_GET['id']) ? $_GET['id'] : die(
    json_encode(['message' => 'No ID provided.'])
);

$row = $student->read_single();

if($row) {
    http_response_code(200);
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['message' => 'Student not found.']);
}