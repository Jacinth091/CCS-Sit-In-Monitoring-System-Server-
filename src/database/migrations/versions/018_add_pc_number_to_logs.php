<?php

class AddPcNumberToLogs {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            ALTER TABLE sit_in_logs 
            ADD COLUMN IF NOT EXISTS pc_number VARCHAR(20)
        ");
    }
}