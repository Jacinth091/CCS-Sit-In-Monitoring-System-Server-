<?php

class AddUniqueConstraintToFeedback {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Enforce that a student can only have one feedback per sit-in session
        $this->db->exec("ALTER TABLE feedback ADD CONSTRAINT unique_sit_in_id UNIQUE (sit_in_id)");
    }
}
