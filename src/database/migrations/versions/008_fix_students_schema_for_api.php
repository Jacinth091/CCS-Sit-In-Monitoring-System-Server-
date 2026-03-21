<?php

class FixStudentsSchemaForApi {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $this->db->exec("CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\"");

        // Ensure all columns expected by src/models/student.php and related APIs exist
        $this->db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS id UUID");
        $this->db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS session INT DEFAULT 30");
        $this->db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS address TEXT");
        $this->db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS profile_pic TEXT");
        $this->db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

        // Backfill id for existing records, then set default for new rows
        $this->db->exec("UPDATE students SET id = uuid_generate_v4() WHERE id IS NULL");
        $this->db->exec("ALTER TABLE students ALTER COLUMN id SET DEFAULT uuid_generate_v4()");

        // Try to enforce NOT NULL on id when possible
        $this->db->exec("ALTER TABLE students ALTER COLUMN id SET NOT NULL");

        // Guarantee uniqueness of id even if another primary key already exists
        $this->db->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_students_id ON students(id)");
    }
}
