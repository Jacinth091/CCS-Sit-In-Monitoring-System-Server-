<?php

class CreateSitInLogsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS sit_in_logs (
                id          UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
                student_id  VARCHAR(50) REFERENCES students(student_id) ON DELETE SET NULL,
                lab_id      INT REFERENCES laboratories(id) ON DELETE SET NULL,
                purpose     VARCHAR(255),
                time_in     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                time_out    TIMESTAMP NULL,
                status      VARCHAR(20) DEFAULT 'ongoing',
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL
            )
        ");
    }
}