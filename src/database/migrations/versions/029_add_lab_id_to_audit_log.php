<?php

class AddLabIdToAuditLog {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            ALTER TABLE reservation_audit_log 
            ADD COLUMN IF NOT EXISTS lab_id INTEGER REFERENCES laboratories(id) ON DELETE SET NULL
        ");
    }
}
