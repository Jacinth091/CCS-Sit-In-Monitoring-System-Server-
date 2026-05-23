<?php

class CreateLabRulesTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS lab_rules (
                id           SERIAL PRIMARY KEY,
                title        VARCHAR(255) NOT NULL,
                description  TEXT NOT NULL,
                icon_name    VARCHAR(100) DEFAULT 'Shield',
                display_order INTEGER DEFAULT 0,
                is_active    BOOLEAN DEFAULT TRUE,
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}