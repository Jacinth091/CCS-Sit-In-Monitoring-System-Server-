<?php

    // $db_user = "postgres";
    // $db_password = "root"
    // $db_name = "sit-in-db";

    $host        = '127.0.0.1';
    $port        = '5432';
    $db_name     = 'sitIn';
    $db_username = 'postgres';
    $db_password = 'root';

    $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $db_name;

    try {
        $db = new PDO($dsn, $db_username, $db_password); 
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        define("SIT_IN", "CCS Sit-In Monitoring System"); 
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array('message' => 'Database Connection Error: ' . $e->getMessage()));
        exit();
    }
?>