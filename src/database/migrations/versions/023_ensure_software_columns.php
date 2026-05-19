<?php

class EnsureSoftwareColumns {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        $cols = [
            'description' => 'TEXT',
            'version' => 'VARCHAR(50)',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'deleted_at' => 'TIMESTAMP NULL'
        ];

        foreach ($cols as $col => $type) {
            try {
                $this->db->exec("ALTER TABLE software ADD COLUMN IF NOT EXISTS $col $type");
            } catch (Exception $e) {
                // Ignore if already exists or other issues
            }
        }
    }
}
