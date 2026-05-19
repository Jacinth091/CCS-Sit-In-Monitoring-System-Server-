<?php

class CreateSoftwareTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Drop many-to-many if it exists from my previous attempt
        $this->db->exec("DROP TABLE IF EXISTS lab_software CASCADE");
        $this->db->exec("DROP TABLE IF EXISTS software CASCADE");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS software (
                id SERIAL PRIMARY KEY,
                lab_id INT NOT NULL REFERENCES laboratories(id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                version VARCHAR(50),
                description TEXT,
                created_at TIMESTAMP DEFAULT NOW()
            )
        ");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_software_lab ON software(lab_id)");
    }
}