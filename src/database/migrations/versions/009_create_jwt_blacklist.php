<?php

class CreateJwtBlacklist {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS jwt_blacklist (
                id SERIAL PRIMARY KEY,
                jti VARCHAR(255) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL, 
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}