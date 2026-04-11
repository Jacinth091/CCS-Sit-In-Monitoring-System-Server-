<?php

class AlterAnnouncementsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Check if columns exist before adding to avoid errors on re-run
        $this->db->exec("
            ALTER TABLE announcements 
            ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'published' CHECK (status IN ('draft', 'published', 'archived')),
            ADD COLUMN IF NOT EXISTS is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
            ADD COLUMN IF NOT EXISTS admin_username VARCHAR(50) DEFAULT 'admin'
        ");

        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_announcements_status ON announcements(status)");
    }
}
