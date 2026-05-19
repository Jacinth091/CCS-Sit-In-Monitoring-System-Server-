<?php

class CleanupReservationColumns {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Drop the redundant time_slot column if it exists
        try {
            $this->db->exec("ALTER TABLE reservations DROP COLUMN IF EXISTS time_slot");
        } catch (Exception $e) {
            // Ignore if already dropped or failed
        }
    }
}
