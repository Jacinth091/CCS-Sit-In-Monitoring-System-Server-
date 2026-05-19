<?php

class SoftwareRequest {
    private $conn;
    private $table = 'student_software_requests';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($student_id, $software_name, $reason = null, $lab_id = null) {
        $query = "INSERT INTO " . $this->table . " (student_id, lab_id, software_name, reason) 
                  VALUES (:student_id, :lab_id, :software_name, :reason)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':lab_id', $lab_id, $lab_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':software_name', $software_name);
        $stmt->bindParam(':reason', $reason);
        
        return $stmt->execute();
    }

    public function getAll($status = null) {
        $query = "SELECT sr.*, 
                         s.first_name, s.last_name, s.course,
                         l.name as lab_name
                  FROM " . $this->table . " sr
                  JOIN students s ON sr.student_id = s.student_id
                  LEFT JOIN laboratories l ON sr.lab_id = l.id";
        
        if ($status !== null) {
            $query .= " WHERE sr.status = :status";
        }
        
        $query .= " ORDER BY sr.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if ($status !== null) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByStudent($student_id) {
        $query = "SELECT sr.*, l.name as lab_name
                  FROM " . $this->table . " sr
                  LEFT JOIN laboratories l ON sr.lab_id = l.id
                  WHERE sr.student_id = :student_id
                  ORDER BY sr.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function bulkUpdateStatus($ids, $status) {
        if (empty($ids)) return false;
        
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $query = "UPDATE " . $this->table . " SET status = ? WHERE id IN (" . $inQuery . ")";
        
        $stmt = $this->conn->prepare($query);
        
        $params = array_merge([$status], $ids);
        return $stmt->execute($params);
    }

    public function getSummary() {
        $query = "SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_requests
                  FROM " . $this->table;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
