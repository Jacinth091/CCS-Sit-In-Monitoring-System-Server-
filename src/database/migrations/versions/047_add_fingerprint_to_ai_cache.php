<?php

class AddFingerprintToAiCache {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function up() {
        try {
            $this->db->exec("ALTER TABLE ai_report_cache ADD COLUMN IF NOT EXISTS data_fingerprint VARCHAR(64)");
        } catch (Exception $e) {
            // Suppress if already exists
        }
    }
}
