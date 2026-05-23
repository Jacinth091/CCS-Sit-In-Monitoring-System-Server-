<?php

class Leaderboard {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getTopStudents($metric = 'hours', $period = 'monthly', $limit = 20) {
        $dateFilter = "";
        
        if ($period === 'weekly') {
            // PostgreSQL specific weekly filter using ISO week
            $dateFilter = "AND sl.time_in >= date_trunc('week', CURRENT_DATE) AND sl.time_in <= CURRENT_DATE + interval '1 day'";
        } elseif ($period === 'monthly') {
            $dateFilter = "AND sl.time_in >= date_trunc('month', CURRENT_DATE) AND sl.time_in <= CURRENT_DATE + interval '1 day'";
        }

        if ($metric === 'sessions') {
            $query = "
                SELECT 
                    sl.student_id, 
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    s.profile_pic,
                    COUNT(sl.id) AS value
                FROM sit_in_logs sl
                JOIN students s ON sl.student_id = s.student_id
                WHERE sl.status = 'completed' AND sl.time_out IS NOT NULL AND sl.deleted_at IS NULL
                $dateFilter
                GROUP BY sl.student_id, s.first_name, s.last_name, s.profile_pic
                ORDER BY value DESC
                LIMIT :limit
            ";
        } else {
            // metric === 'hours'
            $query = "
                SELECT 
                    sl.student_id, 
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    s.profile_pic,
                    SUM(EXTRACT(EPOCH FROM (sl.time_out - sl.time_in)) / 3600) AS value
                FROM sit_in_logs sl
                JOIN students s ON sl.student_id = s.student_id
                WHERE sl.status = 'completed' AND sl.time_out IS NOT NULL AND sl.deleted_at IS NULL
                $dateFilter
                GROUP BY sl.student_id, s.first_name, s.last_name, s.profile_pic
                ORDER BY value DESC
                LIMIT :limit
            ";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $entries = [];
        $rank = 1;

        foreach ($results as $row) {
            $displayValue = "";
            if ($metric === 'sessions') {
                $count = (int)$row['value'];
                $displayValue = $count . " session" . ($count !== 1 ? "s" : "");
            } else {
                $hoursFloat = (float)$row['value'];
                $h = floor($hoursFloat);
                $m = round(($hoursFloat - $h) * 60);
                $displayValue = $h . "h " . str_pad($m, 2, "0", STR_PAD_LEFT) . "m";
            }

            $entries[] = [
                'rank' => $rank++,
                'student_id' => $row['student_id'],
                'student_name' => $row['student_name'],
                'profile_pic' => $row['profile_pic'],
                'value' => (float)$row['value'],
                'display_value' => $displayValue
            ];
        }

        return $entries;
    }
}
