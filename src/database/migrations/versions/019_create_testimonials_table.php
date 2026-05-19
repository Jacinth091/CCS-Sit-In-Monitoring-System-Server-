<?php

class CreateTestimonialsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS testimonials (
                id           SERIAL PRIMARY KEY,
                student_id   VARCHAR(50) REFERENCES students(student_id) ON DELETE CASCADE,
                content      TEXT NOT NULL,
                is_approved  BOOLEAN DEFAULT FALSE,
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at   TIMESTAMP NULL
            )
        ");
    }
}