<?php

class CreateNotificationsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id             SERIAL PRIMARY KEY,
                student_id     VARCHAR(50) REFERENCES students(student_id) ON DELETE CASCADE,
                admin_username VARCHAR(50) NULL,
                type           VARCHAR(50) NOT NULL CHECK (type IN ('sit_in', 'feedback', 'system', 'announcement')),
                title          VARCHAR(255) NOT NULL,
                message        TEXT NOT NULL,
                is_read        BOOLEAN NOT NULL DEFAULT FALSE,
                reference_id   VARCHAR(255) NULL,
                reference_type VARCHAR(50) NULL,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_admin ON notifications(admin_username)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_student ON notifications(student_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read)");
    }
}
