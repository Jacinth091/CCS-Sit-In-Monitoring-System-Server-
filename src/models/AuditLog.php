<?php

class AuditLog {
    private $conn;
    private $table = 'admin_audit_log';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Write a system-wide audit log entry.
     */
    public function write($eventType, $actorId, $actorRole, $description, $entityType = null, $entityId = null, $labId = null, $action = null) {
        $query = "INSERT INTO " . $this->table . " 
                    (event_type, actor_id, actor_role, description, entity_type, entity_id, lab_id, action)
                  VALUES 
                    (:event_type, :actor_id, :actor_role, :description, :entity_type, :entity_id, :lab_id, :action)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':event_type'  => $eventType,
            ':actor_id'    => $actorId,
            ':actor_role'  => $actorRole,
            ':description' => $description,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':lab_id'      => $labId,
            ':action'      => $action
        ]);
    }

    public function getFiltered($filters = [], $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT 
                    al.*,
                    l.name,
                    CASE 
                        WHEN al.entity_type = 'reservation' THEN (SELECT pc_number FROM reservations WHERE id::text = al.entity_id)
                        WHEN al.entity_type = 'pc' THEN (SELECT pc_number FROM pcs WHERE id::text = al.entity_id)
                        ELSE NULL
                    END as pc_number,
                    CASE 
                        WHEN al.entity_type = 'reservation' THEN (SELECT s.first_name || ' ' || s.last_name FROM reservations r JOIN students s ON r.student_id = s.student_id WHERE r.id::text = al.entity_id)
                        ELSE NULL
                    END as student_name,
                    adm.first_name as admin_first_name,
                    adm.last_name as admin_last_name
                  FROM " . $this->table . " al
                  LEFT JOIN laboratories l ON l.id = al.lab_id
                  LEFT JOIN students adm ON adm.student_id = al.actor_id AND al.actor_role = 'admin'
                  WHERE 1=1";
        
        $params = [];

        if (!empty($filters['event_types'])) {
            $placeholders = [];
            foreach ($filters['event_types'] as $i => $type) {
                $placeholders[] = ":type_$i";
                $params[":type_$i"] = $type;
            }
            $query .= " AND al.event_type IN (" . implode(',', $placeholders) . ")";
        }

        if (!empty($filters['entity_type'])) {
            if (is_array($filters['entity_type'])) {
                $placeholders = [];
                foreach ($filters['entity_type'] as $i => $type) {
                    $placeholders[] = ":ent_$i";
                    $params[":ent_$i"] = $type;
                }
                $query .= " AND al.entity_type IN (" . implode(',', $placeholders) . ")";
            } else {
                $query .= " AND al.entity_type = :entity_type";
                $params[':entity_type'] = $filters['entity_type'];
            }
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND al.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND al.created_at <= :date_to::date + INTERVAL '1 day'";
            $params[':date_to'] = $filters['date_to'];
        }

        $query .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
