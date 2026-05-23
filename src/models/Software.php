<?php

class Software {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($page = null, $per_page = null, $search = null) {
        $params = [];
        $searchClause = "";
        
        if ($search !== null && trim($search) !== "") {
            $searchClause = " AND (s.name ILIKE :search OR s.version ILIKE :search OR s.description ILIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($page === null) {
            $query = "SELECT s.id, s.name, s.version, s.description, s.icon_path, s.is_active, s.created_at,
                             COALESCE(
                                 json_agg(json_build_object('id', l.id, 'name', l.name, 'lab_code', l.lab_code)) 
                                 FILTER (WHERE l.id IS NOT NULL), '[]'
                             ) as labs
                      FROM software s
                      LEFT JOIN lab_software ls ON s.id = ls.software_id
                      LEFT JOIN laboratories l ON ls.lab_id = l.id
                      WHERE s.deleted_at IS NULL
                      " . $searchClause . "
                      GROUP BY s.id
                      ORDER BY s.name";
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$row) {
                $row['labs'] = json_decode($row['labs'], true);
            }
            return $results;
        } else {
            // Count query
            $countQuery = "SELECT COUNT(DISTINCT s.id) FROM software s WHERE s.deleted_at IS NULL" . $searchClause;
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($params as $key => $val) {
                $countStmt->bindValue($key, $val);
            }
            $countStmt->execute();
            $totalRecords = (int)$countStmt->fetchColumn();

            // Paginated query
            $offset = ($page - 1) * $per_page;
            $query = "SELECT s.id, s.name, s.version, s.description, s.icon_path, s.is_active, s.created_at,
                             COALESCE(
                                 json_agg(json_build_object('id', l.id, 'name', l.name, 'lab_code', l.lab_code)) 
                                 FILTER (WHERE l.id IS NOT NULL), '[]'
                             ) as labs
                      FROM software s
                      LEFT JOIN lab_software ls ON s.id = ls.software_id
                      LEFT JOIN laboratories l ON ls.lab_id = l.id
                      WHERE s.deleted_at IS NULL
                      " . $searchClause . "
                      GROUP BY s.id
                      ORDER BY s.name
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$row) {
                $row['labs'] = json_decode($row['labs'], true);
            }
            
            return [
                'records' => $results,
                'meta' => [
                    'total' => $totalRecords,
                    'page' => $page,
                    'per_page' => $per_page,
                    'last_page' => ceil($totalRecords / $per_page)
                ]
            ];
        }
    }

    public function getByLab($lab_id) {
        $query = "SELECT s.* 
                  FROM software s
                  JOIN lab_software ls ON s.id = ls.software_id
                  WHERE ls.lab_id = :lab_id AND s.deleted_at IS NULL AND s.is_active = TRUE
                  ORDER BY s.name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':lab_id', $lab_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function checkDuplicate($name, $version, $exclude_id = null) {
        $query = "SELECT id FROM software WHERE name = :name AND version = :version AND deleted_at IS NULL";
        if ($exclude_id !== null) {
            $query .= " AND id != :exclude_id";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':version', $version);
        if ($exclude_id !== null) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $version, $description = null, $icon_path = null, $is_active = true) {
        $query = "INSERT INTO software (name, version, description, icon_path, is_active) 
                  VALUES (:name, :version, :description, :icon_path, :is_active)
                  RETURNING id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':version', $version);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':icon_path', $icon_path);
        $stmt->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);
        
        if ($stmt->execute()) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['id'];
        }
        return false;
    }

    public function update($id, $name, $version, $description = null, $icon_path = null, $is_active = true) {
        $query = "UPDATE software 
                  SET name = :name, version = :version, description = :description, is_active = :is_active";
        
        if ($icon_path !== null) {
            $query .= ", icon_path = :icon_path";
        }
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':version', $version);
        $stmt->bindParam(':description', $description);
        $stmt->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $id);
        if ($icon_path !== null) {
            $stmt->bindParam(':icon_path', $icon_path);
        }
        
        return $stmt->execute();
    }

    public function syncLabs($software_id, $lab_ids) {
        $this->conn->beginTransaction();
        try {
            // Delete existing
            $del = $this->conn->prepare("DELETE FROM lab_software WHERE software_id = :id");
            $del->execute([':id' => $software_id]);
            
            // Insert new
            if (!empty($lab_ids)) {
                $ins = $this->conn->prepare("INSERT INTO lab_software (software_id, lab_id) VALUES (:sid, :lid)");
                foreach ($lab_ids as $lid) {
                    $ins->execute([':sid' => $software_id, ':lid' => $lid]);
                }
            }
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    public function assignToLab($lab_id, $software_id) {
        try {
            // Check if already assigned
            $check = $this->conn->prepare("SELECT 1 FROM lab_software WHERE software_id = :sid AND lab_id = :lid");
            $check->execute([':sid' => $software_id, ':lid' => $lab_id]);
            if ($check->fetch()) {
                return false; // Already assigned
            }

            $ins = $this->conn->prepare("INSERT INTO lab_software (software_id, lab_id) VALUES (:sid, :lid)");
            return $ins->execute([':sid' => $software_id, ':lid' => $lab_id]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function delete($id) {
        $query = "UPDATE software SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
