<?php

class LabRule {
    private $conn;
    private $table = 'lab_rules';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($include_inactive = false) {
        $query = "SELECT * FROM " . $this->table;
        if (!$include_inactive) {
            $query .= " WHERE is_active = TRUE";
        }
        $query .= " ORDER BY display_order ASC, created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($title, $description, $icon_name = 'Shield', $display_order = 0, $is_active = true) {
        $query = "INSERT INTO " . $this->table . " (title, description, icon_name, display_order, is_active) 
                  VALUES (:title, :description, :icon_name, :display_order, :is_active)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':icon_name', $icon_name);
        $stmt->bindParam(':display_order', $display_order, PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
        
        return $stmt->execute();
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, ['title', 'description', 'icon_name', 'display_order', 'is_active'])) {
                $fields[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($fields)) return false;

        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
