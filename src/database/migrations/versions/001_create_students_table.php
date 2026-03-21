<?php

class CreateStudentsTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\"");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS students (
                id            UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
                student_id    VARCHAR(50) UNIQUE NOT NULL,
                first_name    VARCHAR(100) NOT NULL,
                last_name     VARCHAR(100) NOT NULL,
                middle_name   VARCHAR(100),
                course        VARCHAR(100),
                course_level  VARCHAR(50),
                email         VARCHAR(100) UNIQUE NOT NULL,
                password      VARCHAR(255) NOT NULL,
                is_active     BOOLEAN DEFAULT TRUE,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at    TIMESTAMP NULL
            )
        ");
    }
}