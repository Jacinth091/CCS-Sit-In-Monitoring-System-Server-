<?php

class CreateFeedbackTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS feedback (
                id          UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
                student_id  VARCHAR(50) REFERENCES students(student_id) ON DELETE SET NULL,
                sit_in_id   UUID REFERENCES sit_in_logs(id) ON DELETE SET NULL,
                rating      INT CHECK (rating BETWEEN 1 AND 5),
                comment     TEXT,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL
            )
        ");
    }
}