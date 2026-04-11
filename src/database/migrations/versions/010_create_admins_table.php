<?php

class CreateAdminsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id            UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
                username      VARCHAR(50) UNIQUE NOT NULL,
                first_name    VARCHAR(100) NOT NULL,
                last_name     VARCHAR(100) NOT NULL,
                email         VARCHAR(100) UNIQUE NOT NULL,
                password      VARCHAR(255) NOT NULL,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}