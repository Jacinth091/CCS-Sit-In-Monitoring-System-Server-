<?php

class CreateStudentSoftwareRequests {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS student_software_requests (
                id           SERIAL PRIMARY KEY,
                student_id   VARCHAR(50) NOT NULL REFERENCES students(student_id),
                lab_id       INTEGER REFERENCES laboratories(id) ON DELETE SET NULL,
                software_name VARCHAR(150) NOT NULL,
                reason       TEXT,
                status       VARCHAR(20) NOT NULL DEFAULT 'pending'
                             CHECK (status IN ('pending', 'reviewed')),
                created_at   TIMESTAMP DEFAULT NOW()
            )
        ");
    }
}
