<?php

class CleanupCourseLevel {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Remove " Year" suffix (case-insensitive) from course_level
        // Also remove any extra spaces
        $this->db->exec("
            UPDATE students 
            SET course_level = TRIM(REPLACE(REPLACE(course_level, ' Year', ''), ' year', ''))
            WHERE course_level ILIKE '% Year%'
        ");
    }

    public function down() {
        // No easy way to revert this without knowing which ones had it, 
        // but since we want to remove it permanently, down can be empty or add it back to all.
        // We'll leave it empty as this is a cleanup migration.
    }
}