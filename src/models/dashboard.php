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
        $stmt = $this->conn->query("SELECT COUNT(*) as count FROM sit_in_logs WHERE status = 'ongoing'");
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
        $stats['purpose_distribution'] = $purposes;

        // Student Course Distribution
        $stmt = $this->conn->query("SELECT course as label, COUNT(*) as count FROM students WHERE is_active = TRUE AND course IS NOT NULL AND TRIM(course) != '' GROUP BY course ORDER BY count DESC");
        $courses = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($courses, $row);
        }
        $stats['student_course_distribution'] = $courses;

        // 5. Total Labs
        $stmt = $this->conn->query("SELECT COUNT(*) as count FROM laboratories WHERE deleted_at IS NULL");
        $stats['total_labs'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // 6. Recent Sessions
        $stmt = $this->conn->query("
            SELECT s.first_name, s.last_name, l.lab_name, sil.purpose, sil.time_in, sil.status
            FROM sit_in_logs sil
            JOIN students s ON sil.student_id = s.student_id
            JOIN laboratories l ON sil.lab_id = l.id
            ORDER BY sil.time_in DESC
            LIMIT 5
        ");
        $recent = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($recent, $row);
        }
        $stats['recent_sessions'] = $recent;

        // 7. Lab Usage Stats
        $stmt = $this->conn->query("
            SELECT l.lab_name as label, COUNT(sil.id) as count
            FROM laboratories l
            LEFT JOIN sit_in_logs sil ON l.id = sil.lab_id
            WHERE l.deleted_at IS NULL
            GROUP BY l.id, l.lab_name
            ORDER BY count DESC
        ");
        $labStats = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($labStats, $row);
        }
        $stats['lab_usage'] = $labStats;

        return $stats;
    }
}