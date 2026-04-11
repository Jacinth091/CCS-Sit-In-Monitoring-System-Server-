<?php

class Announcement {
    private $conn;
    private $table = 'announcements';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read() {
        $query = "SELECT id, title, content as body, created_at FROM " . $this->table . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $announcements = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['date'] = date('Y-M-d', strtotime($row['created_at']));
            $row['author'] = 'CCS Admin';
            array_push($announcements, $row);
        }
        return $announcements;
    }
}