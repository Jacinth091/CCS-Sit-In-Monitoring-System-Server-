<?php

class CreateReservationsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS reservations (
                id             UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
                student_id     VARCHAR(50) REFERENCES students(student_id) ON DELETE SET NULL,
                lab_id         INT REFERENCES laboratories(id) ON DELETE SET NULL,
                purpose        VARCHAR(255),
                reserved_date  DATE NOT NULL,
                reserved_time  TIME NOT NULL,
                status         VARCHAR(20) DEFAULT 'pending',
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at     TIMESTAMP NULL
            )
        ");
    }
}