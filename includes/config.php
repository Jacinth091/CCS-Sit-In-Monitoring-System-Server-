<?php
// includes/database.php

require_once __DIR__ . '/env.php';

// Load the .env from project root (one level up from includes/)
load_env(__DIR__ . '/../.env');

$host        = $_ENV['DB_HOST'];
$port        = $_ENV['DB_PORT'];
$db_name     = $_ENV['DB_NAME'];
$db_username = $_ENV['DB_USERNAME'];
$db_password = $_ENV['DB_PASSWORD'];

$dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $db_name;

try {
    $db = new PDO($dsn, $db_username, $db_password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    define('SIT_IN', $_ENV['APP_NAME']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database Connection Error: ' . $e->getMessage()]);
    exit();
}