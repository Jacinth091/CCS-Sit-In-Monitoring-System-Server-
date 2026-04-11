<?php
require_once __DIR__ . '../../../includes/cors.php'; 
    require_once __DIR__ . '../../../includes/initialize.php';
    require_once __DIR__ . '../../../includes/validate_token.php';

$currentUser = authenticate();


$student = new Student($db);

// // Expect id from query string: /read_single.php?id=<uuid>
// $student->id = isset($_GET['id']) ? $_GET['id'] : die(
//     json_encode(['message' => 'No ID provided.'])
// );
$student->id = $currentUser->id;
 
$row = $student->read_single();

if($row) {
    sendSuccess(200, 'Student data retrieved.', $row);
} else {
    sendError(404, 'Student not found.');
}