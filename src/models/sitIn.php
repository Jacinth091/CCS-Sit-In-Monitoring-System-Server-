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

    public function __construct($db) {
        $this->conn = $db;
    }

    // GET all records — joins students + laboratories
    public function getAllRecords() {
        $query = 'SELECT
                    sl.id           AS log_id,
                    sl.purpose,
                    sl.time_in,
                    sl.time_out,
                    sl.status,
                    s.student_id,
                    s.first_name,
                    s.last_name,
                    s.course,
                    s.course_level,
                    s.profile_pic,
                    l.lab_name
                  FROM ' . $this->table . ' sl
                  LEFT JOIN students    s ON sl.student_id = s.student_id
                  LEFT JOIN laboratories l ON sl.lab_id    = l.id
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
                    s.student_id,
                    s.first_name,
                    s.last_name,
                    s.course,
                    s.course_level,
                    s.profile_pic,
                    l.lab_name
                  FROM ' . $this->table . ' sl
                  LEFT JOIN students     s ON sl.student_id = s.student_id
                  LEFT JOIN laboratories l ON sl.lab_id     = l.id
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
                    (student_id, lab_id, purpose, time_in, status)
                  VALUES
                    (:student_id, :lab_id, :purpose, NOW(), \'ongoing\')
                  RETURNING id';

        $stmt = $this->conn->prepare($query);

        $this->student_id = htmlspecialchars(strip_tags($this->student_id));
        $this->purpose    = htmlspecialchars(strip_tags($this->purpose));

        $stmt->bindParam(':student_id', $this->student_id);
        $stmt->bindParam(':lab_id',     $this->lab_id);
        $stmt->bindParam(':purpose',    $this->purpose);

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
                    l.lab_name
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
}