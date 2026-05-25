<?php

class CreateAiReportCacheTable {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_report_cache (
                id           SERIAL PRIMARY KEY,
                cache_key    VARCHAR(100) NOT NULL UNIQUE,
                report_type  VARCHAR(50)  NOT NULL,
                payload      JSONB        NOT NULL,
                generated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                expires_at   TIMESTAMP    NOT NULL,
                model_used   VARCHAR(100),
                tokens_used  INTEGER,
                hit_count    INTEGER      DEFAULT 0
            )
        ");

        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_ai_cache_key ON ai_report_cache (cache_key)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_ai_cache_expires ON ai_report_cache (expires_at)");
    }
}
