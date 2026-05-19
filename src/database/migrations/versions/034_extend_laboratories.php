<?php

class ExtendLaboratories {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        try {
            $this->db->exec("ALTER TABLE laboratories RENAME COLUMN lab_name TO name");
        } catch (Exception $e) {}

        try {
            $this->db->exec("ALTER TABLE laboratories RENAME COLUMN is_available TO is_active");
        } catch (Exception $e) {}

        try {
            $this->db->exec("ALTER TABLE laboratories ADD COLUMN IF NOT EXISTS lab_code VARCHAR(50)");
        } catch (Exception $e) {}
    }
}
