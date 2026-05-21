<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';
require_once __DIR__ . '/../../../includes/validate_token.php';

$currentUser = authenticate();


$student = new Student($db);

$id = $currentUser->id;
 
$row = $student->read_by_id($id);

if($row) {
    sendSuccess(200, 'Student data retrieved.', $row);
} else {
    sendError(404, 'Student not found.');
}
