<?php

class AddIsImportantToAnnouncements {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            ALTER TABLE announcements 
            ADD COLUMN IF NOT EXISTS is_important BOOLEAN NOT NULL DEFAULT FALSE
        ");
    }

    public function down() {
        $this->db->exec("
            ALTER TABLE announcements 
            DROP COLUMN IF EXISTS is_important
        ");
    }
}