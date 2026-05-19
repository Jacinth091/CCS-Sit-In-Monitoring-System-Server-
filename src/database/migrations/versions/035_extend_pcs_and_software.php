<?php

class ExtendPcsAndSoftware {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        try {
            $this->db->exec("ALTER TABLE pcs ADD COLUMN IF NOT EXISTS notes TEXT");
        } catch (Exception $e) {}

        try {
            $this->db->exec("ALTER TABLE software ADD COLUMN IF NOT EXISTS icon_path VARCHAR(255)");
        } catch (Exception $e) {}

        try {
            $this->db->exec("ALTER TABLE software ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE");
        } catch (Exception $e) {}
    }
}
