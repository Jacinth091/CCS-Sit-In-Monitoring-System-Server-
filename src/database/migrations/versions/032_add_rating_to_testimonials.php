<?php

class AddRatingToTestimonials {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            ALTER TABLE testimonials 
            ADD COLUMN IF NOT EXISTS rating INT DEFAULT 5 CHECK (rating BETWEEN 1 AND 5)
        ");
    }
}
