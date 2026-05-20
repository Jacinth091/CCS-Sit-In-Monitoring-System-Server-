<?php

class AddImagePathsToTables {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            ALTER TABLE software       ADD COLUMN IF NOT EXISTS icon_path VARCHAR(255);
            ALTER TABLE students       ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255);
            ALTER TABLE laboratories   ADD COLUMN IF NOT EXISTS image_path VARCHAR(255);
        ");
    }
}