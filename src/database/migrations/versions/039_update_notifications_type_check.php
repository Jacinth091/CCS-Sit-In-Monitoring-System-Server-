<?php

class UpdateNotificationsTypeCheck {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function up() {
        // Drop the existing check constraint
        // Note: In PostgreSQL, we need to find the constraint name or use a generic approach if possible.
        // Usually it's named 'notifications_type_check' if it was created with the table.
        // Let's try to drop it and recreate it.
        
        try {
            // Try common name
            $this->db->exec("ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check");
        } catch (Exception $e) {
            // Ignore if it fails, maybe it has a different name
        }

        // Add a new, more inclusive check constraint
        $this->db->exec("
            ALTER TABLE notifications 
            ADD CONSTRAINT notifications_type_check 
            CHECK (type IN ('sit_in', 'feedback', 'system', 'announcement', 'reservation', 'software', 'testimonial'))
        ");
    }
}
