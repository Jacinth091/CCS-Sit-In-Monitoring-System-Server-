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

        // 4. Purpose Distribution (Top 5 + Others)
        $stmt = $this->conn->query("
            SELECT COALESCE(NULLIF(TRIM(purpose), ''), 'Unknown') as label, COUNT(*) as count 
            FROM sit_in_logs 
            GROUP BY label 
            ORDER BY count DESC
        ");
        
        $allPurposes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $topPurposes = array_slice($allPurposes, 0, 5);
        $remainingPurposes = array_slice($allPurposes, 5);
        
        if (!empty($remainingPurposes)) {
            $othersCount = array_sum(array_column($remainingPurposes, 'count'));
            array_push($topPurposes, ['label' => 'Others', 'count' => $othersCount]);
        }
        
        $stats['purpose_distribution'] = $topPurposes;

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
            SELECT s.first_name, s.last_name, l.lab_code, l.name as lab_name, sil.purpose, sil.time_in, sil.status, sil.pc_number
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
            SELECT COALESCE(NULLIF(l.lab_code, ''), l.name) as label, COUNT(sil.id) as count
            FROM laboratories l
            LEFT JOIN sit_in_logs sil ON l.id = sil.lab_id
            WHERE l.deleted_at IS NULL
            GROUP BY l.id, l.lab_code, l.name
            ORDER BY count DESC
        ");
        $labStats = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($labStats, $row);
        }
        $stats['lab_usage'] = $labStats;

        // 8. Dynamic Analytics (Peak Lab & Avg Duration)
        $stats['peak_lab'] = !empty($labStats) ? $labStats[0]['label'] : 'N/A';
        
        $stmt = $this->conn->query("
            SELECT AVG(EXTRACT(EPOCH FROM (time_out - time_in)) / 60) as avg_min 
            FROM sit_in_logs 
            WHERE time_out IS NOT NULL
        ");
        $avgRes = $stmt->fetch(PDO::FETCH_ASSOC);
        $avgMin = $avgRes['avg_min'] ?? 0;
        
        if ($avgMin >= 60) {
            $stats['avg_duration'] = round($avgMin / 60, 1) . ' Hours';
        } else {
            $stats['avg_duration'] = round($avgMin) . ' Mins';
        }

        return $stats;
    }

    public function getSessionsByPurpose($from, $to) {
        $query = "
            SELECT COALESCE(NULLIF(TRIM(purpose), ''), 'Unknown') as label, COUNT(*)::int as count 
            FROM sit_in_logs 
            WHERE time_in::date BETWEEN :from AND :to 
            AND deleted_at IS NULL
            GROUP BY label 
            ORDER BY count DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':from' => $from, ':to' => $to]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($results as &$row) $row['count'] = (int)$row['count'];
        return $results;
    }

    public function getSessionsByLab($from, $to) {
        $query = "
            SELECT l.lab_code, l.name as label, COUNT(sil.id)::int as count
            FROM laboratories l
            LEFT JOIN sit_in_logs sil ON l.id = sil.lab_id 
                AND sil.time_in::date BETWEEN :from AND :to
                AND sil.deleted_at IS NULL
            WHERE l.deleted_at IS NULL
            GROUP BY l.id, l.lab_code, l.name
            ORDER BY count DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':from' => $from, ':to' => $to]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($results as &$row) $row['count'] = (int)$row['count'];
        return $results;
    }

    public function getSessionsByCourse($from, $to) {
        $query = "
            SELECT s.course as label, COUNT(sil.id)::int as count
            FROM sit_in_logs sil
            JOIN students s ON sil.student_id = s.student_id
            WHERE sil.time_in::date BETWEEN :from AND :to
            AND sil.deleted_at IS NULL
            AND s.course IS NOT NULL AND s.course != ''
            GROUP BY s.course
            ORDER BY count DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':from' => $from, ':to' => $to]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($results as &$row) $row['count'] = (int)$row['count'];
        return $results;
    }

    public function getDailyTrend($days) {
        $query = "
            SELECT d.date::date as label, COUNT(sil.id)::int as count
            FROM (
                SELECT CURRENT_DATE - (i || ' days')::interval as date
                FROM generate_series(0, :days) i
            ) d
            LEFT JOIN sit_in_logs sil ON sil.time_in::date = d.date::date AND sil.deleted_at IS NULL
            GROUP BY d.date
            ORDER BY d.date ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':days' => (int)$days]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($results as &$row) $row['count'] = (int)$row['count'];
        return $results;
    }

    public function getPeakHours($from, $to) {
        $query = "
            SELECT 
                EXTRACT(HOUR FROM time_in)::int as hour, 
                COUNT(*)::int as count
            FROM sit_in_logs
            WHERE time_in::date BETWEEN :from AND :to
            AND deleted_at IS NULL
            GROUP BY hour
            ORDER BY count DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':from' => $from, ':to' => $to]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted = [];
        foreach($results as $row) {
            $h = (int)$row['hour'];
            $formatted[] = [
                'hour'  => $h,
                'label' => $h . ':00',
                'count' => (int)$row['count']
            ];
        }
        return $formatted;
    }
}