<?php

class AddReservationIdToSitInLogs {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Add reservation_id to sit_in_logs
        $this->db->exec("
            ALTER TABLE sit_in_logs 
            ADD COLUMN IF NOT EXISTS reservation_id UUID REFERENCES reservations(id) ON DELETE SET NULL
        ");

        // Verify if a constraint exists and update it, else we just rely on application logic.
        // As per Phase 0 findings, there is no existing reservations_status_check constraint, 
        // the status column is just VARCHAR(20) DEFAULT 'pending'.
    }
}
