<?php

class AlterReservationsAddColumns {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Add missing columns to existing reservations table
        $this->db->exec("
            ALTER TABLE reservations 
            ADD COLUMN IF NOT EXISTS pc_number INT,
            ADD COLUMN IF NOT EXISTS time_slot TIME,
            ADD COLUMN IF NOT EXISTS admin_note TEXT
        ");

        // Create indexes
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_reservations_student ON reservations(student_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_reservations_date_lab ON reservations(reserved_date, lab_id)");

        // System settings table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                key VARCHAR(100) PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");

        $this->db->exec("
            INSERT INTO system_settings (key, value) 
            VALUES ('reservations_enabled', 'true')
            ON CONFLICT (key) DO NOTHING
        ");
    }
}