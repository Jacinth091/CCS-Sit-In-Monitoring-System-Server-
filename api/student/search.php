<?php
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

$student = new Student($db);

// Expect keyword from query string: /search.php?q=john
$keyword = isset($_GET['q']) ? $_GET['q'] : die(
    json_encode(['message' => 'No search keyword provided.'])
);

$result = $student->search($keyword);
$num = $result->rowCount();

if($num > 0) {
    $students_arr = array();
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        array_push($students_arr, $row);
    }
    http_response_code(200);
    echo json_encode($students_arr);
} else {
    http_response_code(404);
    echo json_encode(['message' => 'No students found.']);
}