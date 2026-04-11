<?php

class RenameAdminSitInFeedbacksTable {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // The down() method is not implemented, this is a one-way migration for simplicity.
        // We will also change the original migration file for fresh DBs.
        try {
            $this->db->exec("
                ALTER TABLE admin_sit_in_feedbacks 
                RENAME TO admin_feedback
            ");
        } catch (PDOException $e) {
            // If the table doesn't exist, we can ignore the error.
            // This might happen during a fresh migration where the old table was never created.
            if (!str_contains($e->getMessage(), 'does not exist')) {
                throw $e;
            }
        }
    }
}
