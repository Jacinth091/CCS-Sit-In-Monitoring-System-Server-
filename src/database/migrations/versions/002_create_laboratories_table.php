<?php

class CreateLaboratoriesTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS laboratories (
                id            SERIAL PRIMARY KEY,
                lab_name      VARCHAR(100) NOT NULL,
                capacity      INT DEFAULT 30,
                is_available  BOOLEAN DEFAULT TRUE,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at    TIMESTAMP NULL
            )
        ");
    }
}