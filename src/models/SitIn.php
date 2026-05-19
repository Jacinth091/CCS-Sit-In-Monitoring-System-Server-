<?php

class SitIn {
    private $conn;
    private $table = 'sit_in_logs';

    public $id;
    public $student_id;
    public $lab_id;
    public $purpose;
    public $time_in;
    public $time_out;
    public $status;
    public $pc_number;

    public function __construct($db) {
        $this->conn = $db;
    }

    // GET all records — joins students + laboratories + feedback
    public function getAllRecords() {
        $query = 'SELECT
                    sl.id           AS log_id,
                    sl.purpose,
                    sl.time_in,
                    sl.time_out,
                    sl.status,
                    sl.pc_number,
                    s.student_id,
                    s.first_name,
                    s.last_name,
                    s.course,
                    s.course_level,
                    s.profile_pic,
                    l.name,
                    l.lab_code,
                    f.rating AS student_rating,
                    f.comment AS student_comment,
                    af.feedback_text AS admin_remark
                  FROM ' . $this->table . ' sl
                  LEFT JOIN students    s ON sl.student_id = s.student_id
                  LEFT JOIN laboratories l ON sl.lab_id    = l.id
                  LEFT JOIN feedback     f ON f.sit_in_id = sl.id
                  LEFT JOIN admin_feedback af ON af.sit_in_id = sl.id
                  WHERE sl.deleted_at IS NULL
                  ORDER BY sl.time_in DESC';

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // GET single record by id
    public function getSingleRecord() {
        $query = 'SELECT
                    sl.id           AS log_id,
                    sl.purpose,
                    sl.time_in,
                    sl.time_out,
                    sl.status,
                    sl.pc_number,
                    s.student_id,
                    s.first_name,
                    s.last_name,
                    s.course,
                    s.course_level,
                    s.profile_pic,
                    l.name,
                    l.lab_code,
                    f.rating AS student_rating,
                    f.comment AS student_comment,
                    af.feedback_text AS admin_remark
                  FROM ' . $this->table . ' sl
                  LEFT JOIN students     s ON sl.student_id = s.student_id
                  LEFT JOIN laboratories l ON sl.lab_id     = l.id
                  LEFT JOIN feedback      f ON f.sit_in_id = sl.id
                  LEFT JOIN admin_feedback af ON af.sit_in_id = sl.id
                  WHERE sl.id = :id
                  AND sl.deleted_at IS NULL
                  LIMIT 1';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // POST — time in (create new sit-in session)
    public function timeIn() {
        $query = 'INSERT INTO ' . $this->table . '
                    (student_id, lab_id, purpose, pc_number, time_in, status)
                  VALUES
                    (:student_id, :lab_id, :purpose, :pc_number, NOW(), \'ongoing\')
                  RETURNING id';

        $stmt = $this->conn->prepare($query);

        $this->student_id = Validator::sanitizeString($this->student_id);
        $this->purpose    = Validator::sanitizeString($this->purpose);
        $this->pc_number  = Validator::sanitizeString($this->pc_number);

        $stmt->bindParam(':student_id', $this->student_id);
        $stmt->bindParam(':lab_id',     $this->lab_id);
        $stmt->bindParam(':purpose',    $this->purpose);
        $stmt->bindParam(':pc_number',  $this->pc_number);

        try {
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['id'];
        } catch (PDOException $e) {
            echo json_encode(['message' => $e->getMessage()]);
            return false;
        }
    }

    // PUT — time out (update existing session)
    public function timeOut() {
        $query = 'UPDATE ' . $this->table . '
                  SET
                    time_out   = NOW(),
                    status     = \'completed\',
                    updated_at = NOW()
                  WHERE id         = :id
                  AND   student_id = :student_id
                  AND   status     = \'ongoing\'';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id',         $this->id);
        $stmt->bindParam(':student_id', $this->student_id);

        try {
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            echo json_encode(['message' => $e->getMessage()]);
            return false;
        }
    }

    // GET — check if student has an ongoing session
    public function getOngoingSession() {
        $query = 'SELECT
                    sl.id AS log_id,
                    sl.purpose,
                    sl.time_in,
                    sl.status,
                    l.name,
                    l.lab_code
                  FROM ' . $this->table . ' sl
                  LEFT JOIN laboratories l ON sl.lab_id = l.id
                  WHERE sl.student_id = :student_id
                  AND   sl.status     = \'ongoing\'
                  AND   sl.deleted_at IS NULL
                  LIMIT 1';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $this->student_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function isPcInUse($lab_id, $pc_number) {
        $query = "SELECT COUNT(*) FROM " . $this->table . " 
                  WHERE lab_id = :lab_id 
                  AND pc_number = :pc_number 
                  AND status = 'ongoing' 
                  AND deleted_at IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':lab_id' => $lab_id,
            ':pc_number' => $pc_number
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    public function getOccupiedPcs($lab_id) {
        $query = "SELECT pc_number FROM " . $this->table . " 
                  WHERE lab_id = :lab_id 
                  AND status = 'ongoing' 
                  AND deleted_at IS NULL 
                  AND pc_number IS NOT NULL";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':lab_id' => $lab_id]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Soft delete
    public function delete() {
        $query = 'UPDATE ' . $this->table . '
                  SET deleted_at = NOW()
                  WHERE id = :id';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        try {
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            echo json_encode(['message' => $e->getMessage()]);
            return false;
        }
    }

    public function getStudentSummary($student_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) AS total_sessions,
                COALESCE(SUM(EXTRACT(EPOCH FROM (time_out - time_in)) / 60), 0) AS total_minutes,
                COALESCE(AVG(EXTRACT(EPOCH FROM (time_out - time_in)) / 60), 0) AS avg_minutes,
                COALESCE(MAX(EXTRACT(EPOCH FROM (time_out - time_in)) / 60), 0) AS longest_minutes
            FROM sit_in_logs
            WHERE student_id = :student_id 
              AND time_out IS NOT NULL 
              AND deleted_at IS NULL
        ");
        $stmt->execute([':student_id' => $student_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}