<?php
// api/lab/read.php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$query = 'SELECT * FROM laboratories ORDER BY lab_name ASC';
$stmt = $db->prepare($query);
$stmt->execute();

$labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode($labs);
