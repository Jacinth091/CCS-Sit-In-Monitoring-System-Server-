<?php

class CreateAiSecurityTables {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Create user_sessions table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_sessions (
                id           SERIAL PRIMARY KEY,
                user_id      INTEGER NOT NULL,
                role         VARCHAR(20) NOT NULL CHECK (role IN ('student', 'admin')),
                token_hash   VARCHAR(64) NOT NULL UNIQUE,
                device_fingerprint VARCHAR(255),
                ip_address   INET,
                created_at   TIMESTAMP DEFAULT NOW(),
                expires_at   TIMESTAMP NOT NULL,
                last_used_at TIMESTAMP DEFAULT NOW(),
                is_active    BOOLEAN NOT NULL DEFAULT TRUE
            )
        ");

        // Create indexes on user_sessions
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_us_token_hash ON user_sessions(token_hash)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_us_user_id ON user_sessions(user_id, is_active)");

        // Create ai_usage_log table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_usage_log (
                id           SERIAL PRIMARY KEY,
                user_id      INTEGER NOT NULL,
                role         VARCHAR(20) NOT NULL,
                endpoint     VARCHAR(50) NOT NULL,
                tokens_used  INTEGER,
                requested_at TIMESTAMP DEFAULT NOW(),
                was_blocked  BOOLEAN NOT NULL DEFAULT FALSE,
                block_reason VARCHAR(50)
            )
        ");

        // Create indexes on ai_usage_log
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_aul_user_date ON ai_usage_log(user_id, requested_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_aul_endpoint_date ON ai_usage_log(endpoint, requested_at)");

        // Create ai_abuse_log table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_abuse_log (
                id            SERIAL PRIMARY KEY,
                identifier    VARCHAR(255) NOT NULL,
                failure_type  VARCHAR(30) NOT NULL,
                ip_address    INET,
                attempted_at  TIMESTAMP DEFAULT NOW()
            )
        ");

        // Create indexes on ai_abuse_log
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_aal_identifier ON ai_abuse_log(identifier, attempted_at)");

        // Create ai_global_budget table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_global_budget (
                id           SERIAL PRIMARY KEY,
                budget_date  DATE NOT NULL UNIQUE DEFAULT CURRENT_DATE,
                chat_calls   INTEGER NOT NULL DEFAULT 0,
                analysis_calls INTEGER NOT NULL DEFAULT 0,
                summary_calls  INTEGER NOT NULL DEFAULT 0
            )
        ");
    }
}
