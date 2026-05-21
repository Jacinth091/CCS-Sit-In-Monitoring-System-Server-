<?php

class CreateAdminAuditLog {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // 1. Rename the existing table if it exists and target doesn't
        $tableExists = $this->db->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'reservation_audit_log'")->fetch();
        $targetExists = $this->db->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'admin_audit_log'")->fetch();

        if ($tableExists && !$targetExists) {
            $this->db->exec("ALTER TABLE reservation_audit_log RENAME TO admin_audit_log");
        } elseif ($tableExists && $targetExists) {
            // If both exist, we might have a stray empty reservation_audit_log
            // Check if reservation_audit_log is empty before dropping it to be safe
            $count = $this->db->query("SELECT COUNT(*) FROM reservation_audit_log")->fetchColumn();
            if ($count == 0) {
                $this->db->exec("DROP TABLE reservation_audit_log");
            }
        }

        // 2. Add generic columns (IF NOT EXISTS is already there)
        $this->db->exec("
            ALTER TABLE admin_audit_log 
            ADD COLUMN IF NOT EXISTS entity_type VARCHAR(50),
            ADD COLUMN IF NOT EXISTS entity_id VARCHAR(50)
        ");

        // 3. Migrate existing data to generic columns (Safe to run multiple times)
        // Only update if entity_type is null to avoid overwriting
        
        // Check if reservation_id column exists before trying to use it in UPDATE
        $resIdExists = $this->db->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'admin_audit_log' AND column_name = 'reservation_id'")->fetch();
        if ($resIdExists) {
            $this->db->exec("
                UPDATE admin_audit_log 
                SET entity_type = 'reservation', entity_id = CAST(reservation_id AS VARCHAR)
                WHERE entity_type IS NULL AND reservation_id IS NOT NULL
            ");
        }

        // Check if pc_id column exists before trying to use it in UPDATE
        $pcIdExists = $this->db->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'admin_audit_log' AND column_name = 'pc_id'")->fetch();
        if ($pcIdExists) {
            $this->db->exec("
                UPDATE admin_audit_log 
                SET entity_type = 'pc', entity_id = CAST(pc_id AS VARCHAR)
                WHERE pc_id IS NOT NULL AND entity_type IS NULL
            ");
        }

        // 4. Clean up old specific columns
        $this->db->exec("ALTER TABLE admin_audit_log DROP COLUMN IF EXISTS reservation_id");
        $this->db->exec("ALTER TABLE admin_audit_log DROP COLUMN IF EXISTS pc_id");
    }
}
