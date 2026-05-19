<?php

class PC {
    private $conn;
    private $table = 'pcs';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByLab($lab_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE lab_id = :lab_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':lab_id' => $lab_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $stmt = $this->conn->prepare("SELECT reservation_status FROM " . $this->table . " WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pc) {
            return false;
        }

        $currentReservation = $pc['reservation_status'] ?? 'open';
        $nextReservation = $currentReservation;

        if ($status === 'disabled' || $status === 'under maintenance') {
            $nextReservation = 'unavailable';
        } elseif ($status === 'active' && $currentReservation === 'unavailable') {
            $nextReservation = 'open';
        }

        $query = "UPDATE " . $this->table . " 
                  SET pc_status = :status, reservation_status = :reservation_status, updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':status' => $status,
            ':reservation_status' => $nextReservation,
            ':id' => $id
        ]);
    }

    public function updateReservationStatus($id, $status) {
        // First check pc_status
        $stmt = $this->conn->prepare("SELECT pc_status FROM " . $this->table . " WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pc && $pc['pc_status'] !== 'active') {
            return false; // Cannot change reservation status if not active
        }

        $query = "UPDATE " . $this->table . " SET reservation_status = :status, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function upsert($lab_id, $pc_number, $pc_status = 'active', $reservation_status = 'open') {
        $query = "INSERT INTO " . $this->table . " (lab_id, pc_number, pc_status, reservation_status, updated_at)
                  VALUES (:lab_id, :pc_number, :pc_status, :reservation_status, NOW())
                  ON CONFLICT (lab_id, pc_number)
                  DO UPDATE SET pc_status = EXCLUDED.pc_status, 
                                reservation_status = EXCLUDED.reservation_status,
                                updated_at = NOW()
                  RETURNING id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':lab_id' => $lab_id,
            ':pc_number' => $pc_number,
            ':pc_status' => $pc_status,
            ':reservation_status' => $reservation_status
        ]);
        
        return $stmt->fetchColumn();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
    
    public function create($lab_id, $pc_number, $pc_status = 'active', $reservation_status = 'open', $notes = null) {
        $query = "INSERT INTO " . $this->table . " (lab_id, pc_number, pc_status, reservation_status, notes, updated_at)
                  VALUES (:lab_id, :pc_number, :pc_status, :reservation_status, :notes, NOW())
                  RETURNING id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':lab_id' => $lab_id,
            ':pc_number' => $pc_number,
            ':pc_status' => $pc_status,
            ':reservation_status' => $reservation_status,
            ':notes' => $notes
        ]);
        
        return $stmt->fetchColumn();
    }
}
