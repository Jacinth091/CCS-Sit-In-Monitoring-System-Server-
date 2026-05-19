<?php

class AddDetailsToLaboratories {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            ALTER TABLE laboratories 
            ADD COLUMN IF NOT EXISTS description TEXT,
            ADD COLUMN IF NOT EXISTS lab_type VARCHAR(50) DEFAULT 'General Purpose'
        ");
    }
}
