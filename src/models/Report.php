<?php

class Report {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * @param array $filters Keys: from, to, lab_id, purpose, student_id
     * @return array
     */
    public function getSitinReport($filters, $limit = null, $offset = null) {
        $sql = "SELECT 
                    s.student_id,
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    l.name,
                    l.lab_code,
                    sl.pc_number,
                    sl.purpose,
                    sl.time_in,
                    sl.time_out,
                    ROUND(EXTRACT(EPOCH FROM (sl.time_out - sl.time_in)) / 60) AS total_minutes,
                    FLOOR(EXTRACT(EPOCH FROM (sl.time_out - sl.time_in)) / 3600) AS duration_hours,
                    FLOOR(MOD(EXTRACT(EPOCH FROM (sl.time_out - sl.time_in)) / 60, 60)) AS duration_minutes,
                    sl.status
                FROM sit_in_logs sl
                LEFT JOIN students s ON sl.student_id = s.student_id
                LEFT JOIN laboratories l ON sl.lab_id = l.id
                WHERE sl.deleted_at IS NULL AND sl.status != 'ongoing'";

        $params = [];

        if (!empty($filters['from'])) {
            $sql .= " AND sl.time_in >= :from_date";
            $params[':from_date'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= " AND sl.time_in <= :to_date::date + INTERVAL '1 day'";
            $params[':to_date'] = $filters['to'];
        }
        if (!empty($filters['lab_id'])) {
            $sql .= " AND sl.lab_id = :lab_id";
            $params[':lab_id'] = $filters['lab_id'];
        }
        if (!empty($filters['purpose'])) {
            $sql .= " AND sl.purpose = :purpose";
            $params[':purpose'] = $filters['purpose'];
        }
        if (!empty($filters['student_id'])) {
            $sql .= " AND sl.student_id = :student_id";
            $params[':student_id'] = $filters['student_id'];
        }

        $sql .= " ORDER BY sl.time_in DESC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = (int)$limit;
            $params[':offset'] = (int)$offset;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSitinCount($filters) {
        $sql = "SELECT COUNT(*) FROM sit_in_logs sl WHERE deleted_at IS NULL AND status != 'ongoing'";
        $params = [];

        if (!empty($filters['from'])) {
            $sql .= " AND sl.time_in >= :from_date";
            $params[':from_date'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= " AND sl.time_in <= :to_date::date + INTERVAL '1 day'";
            $params[':to_date'] = $filters['to'];
        }
        if (!empty($filters['lab_id'])) {
            $sql .= " AND sl.lab_id = :lab_id";
            $params[':lab_id'] = $filters['lab_id'];
        }
        if (!empty($filters['purpose'])) {
            $sql .= " AND sl.purpose = :purpose";
            $params[':purpose'] = $filters['purpose'];
        }
        if (!empty($filters['student_id'])) {
            $sql .= " AND sl.student_id = :student_id";
            $params[':student_id'] = $filters['student_id'];
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
