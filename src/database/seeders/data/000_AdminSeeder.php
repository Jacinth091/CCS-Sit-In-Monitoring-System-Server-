<?php

class AdminSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO admins (username, first_name, last_name, email, password) 
                VALUES ('admin', 'System', 'Administrator', 'admin@example.com', '$password')
                ON CONFLICT (username) DO NOTHING";
        
        $this->db->exec($sql);
        echo "  → Admin user seeded.\n";
    }
}