<?php

class Laboratory {
    private $conn;
    private $table = 'laboratories';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllWithSoftware() {
        $query = "SELECT * FROM " . $this->table . " WHERE deleted_at IS NULL ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each lab, fetch software and stats
        foreach ($labs as &$lab) {
            try {
                $lab['software'] = $this->getLabSoftware($lab['id']);
            } catch (Exception $e) {
                error_log("Error fetching software for lab " . $lab['id'] . ": " . $e->getMessage());
                $lab['software'] = [];
            }

            try {
                $lab['stats'] = $this->getLabStats($lab['id']);
            } catch (Exception $e) {
                error_log("Error fetching stats for lab " . $lab['id'] . ": " . $e->getMessage());
                $lab['stats'] = [
                    'total_sessions' => 0,
                    'avg_duration' => '0 mins',
                    'top_purpose' => 'N/A'
                ];
            }
        }

        return $labs;
    }

    public function getLabSoftware($lab_id) {
        $query = "SELECT s.* 
                  FROM software s
                  JOIN lab_software ls ON s.id = ls.software_id
                  WHERE ls.lab_id = :lab_id AND s.deleted_at IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':lab_id', $lab_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLabStats($lab_id) {
        $stats = [];

        // Total sessions
        $query = "SELECT COUNT(*) as count FROM sit_in_logs WHERE lab_id = :lab_id AND deleted_at IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':lab_id' => $lab_id]);
        $stats['total_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Average duration
        $query = "SELECT AVG(EXTRACT(EPOCH FROM (time_out - time_in)) / 60) as avg_min 
                  FROM sit_in_logs 
                  WHERE lab_id = :lab_id AND time_out IS NOT NULL AND deleted_at IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':lab_id' => $lab_id]);
        $avgRes = $stmt->fetch(PDO::FETCH_ASSOC);
        $avgMin = $avgRes['avg_min'] ?? 0;
        $stats['avg_duration'] = $avgMin >= 60 ? round($avgMin / 60, 1) . ' hrs' : round($avgMin) . ' mins';

        // Most frequent purpose
        $query = "SELECT purpose, COUNT(*) as count 
                  FROM sit_in_logs 
                  WHERE lab_id = :lab_id AND deleted_at IS NULL 
                  GROUP BY purpose 
                  ORDER BY count DESC 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':lab_id' => $lab_id]);
        $purpose = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['top_purpose'] = $purpose['purpose'] ?? 'N/A';

        return $stats;
    }

    public function create($name, $lab_code = null, $capacity = 30, $is_active = true) {
        $query = "INSERT INTO " . $this->table . " (name, lab_code, capacity, is_active) 
                  VALUES (:name, :lab_code, :capacity, :is_active) RETURNING id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':lab_code', $lab_code);
        $stmt->bindParam(':capacity', $capacity);
        $stmt->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['id'];
        }
        return false;
    }

    public function update($id, $name, $lab_code = null, $capacity = 30, $is_active = true) {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, lab_code = :lab_code, capacity = :capacity, is_active = :is_active, updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':lab_code', $lab_code);
        $stmt->bindParam(':capacity', $capacity);
        $stmt->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    public function delete($id) {
        $query = "UPDATE " . $this->table . " SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
