<?php

class AddIsAnonymousToTestimonials {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("
            ALTER TABLE testimonials 
            ADD COLUMN IF NOT EXISTS is_anonymous BOOLEAN DEFAULT TRUE
        ");
    }
}
