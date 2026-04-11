<?php

class Dashboard {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getStats() {
        $stats = array();

        // 1. Total Students Registered
        $stmt = $this->conn->query('SELECT COUNT(*) as count FROM students WHERE is_active = TRUE');
        $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // 2. Currently Sit-in
        $stmt = $this->conn->query("SELECT COUNT(*) as count FROM sit_in_logs WHERE status = 'Active'");
        $stats['current_sitin'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // 3. Total Sit-in
        $stmt = $this->conn->query("SELECT COUNT(*) as count FROM sit_in_logs");
        $stats['total_sitin'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // 4. Purpose Distribution
        $stmt = $this->conn->query("SELECT COALESCE(NULLIF(TRIM(purpose), ''), 'Unknown') as label, COUNT(*) as count FROM sit_in_logs GROUP BY label ORDER BY count DESC LIMIT 5");
        $purposes = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($purposes, $row);
        }
        $stats['course_distribution'] = $purposes;

        return $stats;
    }
}