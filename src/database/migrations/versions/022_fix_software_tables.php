<?php

class FixSoftwareTables {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Ensure software table exists
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

        // Add missing columns if software already existed without them
        try {
            $this->db->exec("ALTER TABLE software ADD COLUMN IF NOT EXISTS description TEXT");
        } catch (Exception $e) {}
        try {
            $this->db->exec("ALTER TABLE software ADD COLUMN IF NOT EXISTS version VARCHAR(50)");
        } catch (Exception $e) {}
        try {
            $this->db->exec("ALTER TABLE software ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        } catch (Exception $e) {}
        try {
            $this->db->exec("ALTER TABLE software ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL");
        } catch (Exception $e) {}

        // Ensure lab_software table exists
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
