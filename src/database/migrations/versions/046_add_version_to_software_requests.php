<?php

class AddVersionToSoftwareRequests {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function up() {
        try {
            $this->db->exec("ALTER TABLE student_software_requests ADD COLUMN IF NOT EXISTS version VARCHAR(50)");
        } catch (Exception $e) {
            // Suppress if already exists
        }
    }
}
