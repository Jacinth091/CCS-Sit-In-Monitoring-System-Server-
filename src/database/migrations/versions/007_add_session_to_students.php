<?php

class AddSessionToStudents {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            ALTER TABLE students 
            ADD COLUMN IF NOT EXISTS session INT DEFAULT 30
        ");
    }
}