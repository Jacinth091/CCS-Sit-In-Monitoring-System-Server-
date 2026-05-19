<?php

class CreateAdminAuditLog {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // 1. Rename the existing table
        $this->db->exec("ALTER TABLE reservation_audit_log RENAME TO admin_audit_log");

        // 2. Add generic columns
        $this->db->exec("
            ALTER TABLE admin_audit_log 
            ADD COLUMN IF NOT EXISTS entity_type VARCHAR(50),
            ADD COLUMN IF NOT EXISTS entity_id VARCHAR(50)
        ");

        // 3. Migrate existing data to generic columns
        $this->db->exec("
            UPDATE admin_audit_log 
            SET entity_type = 'reservation', entity_id = CAST(reservation_id AS VARCHAR)
            WHERE reservation_id IS NOT NULL
        ");

        $this->db->exec("
            UPDATE admin_audit_log 
            SET entity_type = 'pc', entity_id = CAST(pc_id AS VARCHAR)
            WHERE pc_id IS NOT NULL AND entity_type IS NULL
        ");

        // 4. Clean up old specific columns
        $this->db->exec("ALTER TABLE admin_audit_log DROP COLUMN IF EXISTS reservation_id");
        $this->db->exec("ALTER TABLE admin_audit_log DROP COLUMN IF EXISTS pc_id");
    }
}
