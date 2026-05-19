<?php

class Reservation {
    private $conn;
    private $table = 'reservations';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                    (student_id, lab_id, pc_number, reserved_date, reserved_time, purpose, status)
                  VALUES 
                    (:student_id, :lab_id, :pc_number, :reserved_date, :reserved_time, :purpose, 'pending')
                  RETURNING id";

        $stmt = $this->conn->prepare($query);

        try {
            $stmt->execute([
                ':student_id'    => $data['student_id'],
                ':lab_id'        => $data['lab_id'],
                ':pc_number'     => $data['pc_number'],
                ':reserved_date' => $data['reserved_date'],
                ':reserved_time' => $data['time_slot'],
                ':purpose'       => $data['purpose']
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['id'] ?? false;
        } catch (PDOException $e) {
            error_log("Reservation Create Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function isSlotTaken($lab_id, $pc_number, $date, $time_slot) {
        $query = "SELECT COUNT(*) FROM " . $this->table . " r
                  WHERE r.lab_id = :lab_id 
                  AND r.pc_number = :pc_number 
                  AND r.reserved_date = :date 
                  AND r.status IN ('pending', 'approved', 'rescheduled')
                  AND r.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM sit_in_logs sl
                      WHERE sl.lab_id = r.lab_id
                      AND CAST(sl.pc_number AS text) = CAST(r.pc_number AS text)
                      AND sl.student_id = r.student_id
                      AND sl.status = 'completed'
                      AND sl.time_out IS NOT NULL
                      AND DATE(sl.time_in) = r.reserved_date
                      AND sl.deleted_at IS NULL
                  )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':lab_id' => $lab_id,
            ':pc_number' => $pc_number,
            ':date' => $date
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    public function getOccupiedPcs($lab_id, $date, $time_slot) {
        $query = "SELECT r.pc_number FROM " . $this->table . " r
                  WHERE r.lab_id = :lab_id 
                  AND r.reserved_date = :date 
                  AND r.status IN ('pending', 'approved', 'rescheduled')
                  AND r.pc_number IS NOT NULL
                  AND r.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM sit_in_logs sl
                      WHERE sl.lab_id = r.lab_id
                      AND CAST(sl.pc_number AS text) = CAST(r.pc_number AS text)
                      AND sl.student_id = r.student_id
                      AND sl.status = 'completed'
                      AND sl.time_out IS NOT NULL
                      AND DATE(sl.time_in) = r.reserved_date
                      AND sl.deleted_at IS NULL
                  )";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':lab_id' => $lab_id,
            ':date' => $date
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getByStudent($student_id) {
        $query = "SELECT r.*, l.name, l.lab_code 
                  FROM " . $this->table . " r
                  LEFT JOIN laboratories l ON r.lab_id = l.id
                  WHERE r.student_id = :student_id AND r.deleted_at IS NULL
                  ORDER BY r.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll($filters = []) {
        $query = "SELECT r.*, l.name, l.lab_code, s.first_name, s.last_name, s.course, s.course_level
                  FROM " . $this->table . " r
                  LEFT JOIN laboratories l ON r.lab_id = l.id
                  LEFT JOIN students s ON r.student_id = s.student_id
                  WHERE r.deleted_at IS NULL";

        if (!empty($filters['status'])) {
            $query .= " AND r.status = :status";
        }

        $query .= " ORDER BY r.reserved_date ASC, r.reserved_time ASC";

        $stmt = $this->conn->prepare($query);
        if (!empty($filters['status'])) {
            $stmt->bindParam(':status', $filters['status']);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status, $admin_note = null) {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status, admin_note = :admin_note, updated_at = NOW() 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':admin_note', $admin_note);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    public function cancelByStudent($id, $student_id) {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'cancelled', updated_at = NOW()
                  WHERE id = :id AND student_id = :student_id AND status = 'pending'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':student_id', $student_id);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    public function isPcReservedNow($lab_id, $pc_number) {
        // A reservation is considered "now" if it's for today 
        // and the reserved time is within -30 to +60 minutes of now
        $query = "SELECT COUNT(*) FROM " . $this->table . " 
                  WHERE lab_id = :lab_id 
                  AND pc_number = :pc_number 
                  AND reserved_date = CURRENT_DATE 
                  AND status = 'approved'
                  AND reserved_time BETWEEN (CURRENT_TIME - INTERVAL '30 minutes') AND (CURRENT_TIME + INTERVAL '60 minutes')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':lab_id' => $lab_id,
            ':pc_number' => $pc_number
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    public function getReservationsNow($lab_id) {
        $query = "SELECT pc_number FROM " . $this->table . " 
                  WHERE lab_id = :lab_id 
                  AND reserved_date = CURRENT_DATE 
                  AND status = 'approved'
                  AND reserved_time BETWEEN (CURRENT_TIME - INTERVAL '30 minutes') AND (CURRENT_TIME + INTERVAL '120 minutes')
                  AND pc_number IS NOT NULL";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':lab_id' => $lab_id]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function reschedule($reservationId, $newDate, $newTimeSlot, $newPcNumber = null, $adminNote = null) {
        $updateFields = [
            'reserved_date = :new_date',
            'reserved_time = :new_time',
            'status = \'rescheduled\'',
            'updated_at = NOW()'
        ];
        $params = [
            ':new_date' => $newDate,
            ':new_time' => $newTimeSlot,
            ':id' => $reservationId
        ];

        if ($newPcNumber !== null) {
            $updateFields[] = 'pc_number = :new_pc';
            $params[':new_pc'] = $newPcNumber;
        }

        if ($adminNote !== null) {
            $updateFields[] = 'admin_note = :admin_note';
            $params[':admin_note'] = $adminNote;
        }

        $query = "UPDATE " . $this->table . " SET " . implode(', ', $updateFields) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function isReservationsEnabled() {
        $query = "SELECT value FROM system_settings WHERE key = 'reservations_enabled'";
        $stmt = $this->conn->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row['value'] === 'true';
    }

    public function setReservationsEnabled($enabled) {
        $val = $enabled ? 'true' : 'false';
        $query = "UPDATE system_settings SET value = :val WHERE key = 'reservations_enabled'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':val', $val);
        return $stmt->execute();
    }
}
