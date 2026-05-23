<?php

class CreateCoursesTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS courses (
                id SERIAL PRIMARY KEY,
                code VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed default courses
        $courses = [
            ['code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
            ['code' => 'BSCS', 'name' => 'Bachelor of Science in Computer Science'],
            ['code' => 'BSIS', 'name' => 'Bachelor of Science in Information Systems'],
            ['code' => 'BSCE', 'name' => 'Bachelor of Science in Computer Engineering'],
            ['code' => 'BSCS-AI', 'name' => 'Bachelor of Science in Computer Science - AI']
        ];

        $stmt = $this->db->prepare("
            INSERT INTO courses (code, name) 
            VALUES (:code, :name)
            ON CONFLICT (code) DO NOTHING
        ");

        foreach ($courses as $c) {
            $stmt->execute($c);
        }
    }
}
