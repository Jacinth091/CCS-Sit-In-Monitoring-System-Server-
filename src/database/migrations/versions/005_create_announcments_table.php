<?php

class CreateAnnouncmentsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS announcements (
                id          SERIAL PRIMARY KEY,
                title       VARCHAR(255) NOT NULL,
                content     TEXT NOT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL
            )
        ");
    }
}