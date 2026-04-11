<?php

class CreateAdminFeedbackTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS admin_feedback (
                id              UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
                sit_in_id       UUID NOT NULL REFERENCES sit_in_logs(id) ON DELETE CASCADE,
                admin_username  VARCHAR(50) NOT NULL,
                feedback_text   TEXT NOT NULL CHECK (char_length(feedback_text) <= 500),
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(sit_in_id)
            )
        ");

        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_admin_feedback_sit_in_id ON admin_feedback(sit_in_id)");
    }
}
