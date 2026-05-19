<?php

class CreateAuditLogTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS reservation_audit_log (
                id          SERIAL PRIMARY KEY,
                event_type  VARCHAR(50) NOT NULL,
                actor_id    VARCHAR(50),               -- Can be student_id or admin_id
                actor_role  VARCHAR(20),               -- 'admin' | 'student' | 'system'
                reservation_id UUID REFERENCES reservations(id) ON DELETE SET NULL,
                pc_id       INTEGER REFERENCES pcs(id) ON DELETE SET NULL,
                description TEXT NOT NULL,
                created_at  TIMESTAMP DEFAULT NOW()
            )
        ");
    }
}
