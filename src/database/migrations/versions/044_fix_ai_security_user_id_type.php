<?php

class FixAiSecurityUserIdType {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Change user_id to VARCHAR(50) in user_sessions
        $this->db->exec("ALTER TABLE user_sessions ALTER COLUMN user_id TYPE VARCHAR(50)");
        
        // Change user_id to VARCHAR(50) in ai_usage_log
        $this->db->exec("ALTER TABLE ai_usage_log ALTER COLUMN user_id TYPE VARCHAR(50)");
    }
}
