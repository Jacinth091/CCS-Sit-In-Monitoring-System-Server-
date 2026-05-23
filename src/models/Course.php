<?php

class Course {
    private $conn;
    private $table = 'courses';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read() {
        $query = "SELECT id, code, name, created_at FROM " . $this->table . " ORDER BY code ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
