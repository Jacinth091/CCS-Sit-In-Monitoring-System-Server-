<?php

class MakeSoftwareLabIdNullable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Make lab_id nullable in software table to support many-to-many
        try {
            $this->db->exec("ALTER TABLE software ALTER COLUMN lab_id DROP NOT NULL");
        } catch (Exception $e) {
            // Might already be nullable or column might not exist in some versions
        }
    }
}
