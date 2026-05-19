<?php

class CreatePcsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Drop the previous attempt if it exists
        $this->db->exec("DROP TABLE IF EXISTS pcs");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pcs (
                id              SERIAL PRIMARY KEY,
                lab_id          INTEGER NOT NULL REFERENCES laboratories(id) ON DELETE CASCADE,
                pc_number       INTEGER NOT NULL,
                pc_status       VARCHAR(20) NOT NULL DEFAULT 'active'
                                CHECK (pc_status IN ('active', 'disabled', 'under maintenance')),
                reservation_status VARCHAR(20) NOT NULL DEFAULT 'open'
                                CHECK (reservation_status IN ('open', 'occupied', 'reserved', 'unavailable')),
                updated_at      TIMESTAMP DEFAULT NOW(),
                UNIQUE(lab_id, pc_number)
            )
        ");
    }
}
