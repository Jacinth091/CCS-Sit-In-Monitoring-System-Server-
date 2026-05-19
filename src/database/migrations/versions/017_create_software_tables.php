<?php

class CreateSoftwareTables {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS software (
                id           SERIAL PRIMARY KEY,
                name         VARCHAR(100) NOT NULL,
                description  TEXT,
                version      VARCHAR(50),
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at   TIMESTAMP NULL
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS lab_software (
                id           SERIAL PRIMARY KEY,
                lab_id       INT REFERENCES laboratories(id) ON DELETE CASCADE,
                software_id  INT REFERENCES software(id) ON DELETE CASCADE,
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(lab_id, software_id)
            )
        ");
    }
}