<?php

class SystemAuditLogSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Clear existing logs
        $this->db->exec("TRUNCATE TABLE admin_audit_log RESTART IDENTITY CASCADE");

        // 1. Fetch some existing data to link to
        $reservation = $this->db->query("SELECT id, lab_id FROM reservations LIMIT 1")->fetch();
        $pc = $this->db->query("SELECT id, lab_id FROM pcs LIMIT 1")->fetch();
        $announcement = $this->db->query("SELECT id, title FROM announcements LIMIT 1")->fetch();

        // 2. Sample logs
        $logs = [
            [
                'event' => 'PC status changed',
                'desc' => "CCS Lab 1's (PC #1) Functional State was updated to Disabled",
                'type' => 'pc',
                'eid' => $pc['id'] ?? '1',
                'lid' => $pc['lab_id'] ?? 1,
                'act' => 'Functional State updated to Disabled'
            ],
            [
                'event' => 'Reservation approved',
                'desc' => "Reservation for CCS Lab 1's (PC #5) by Juan Dela Cruz was updated to Approved",
                'type' => 'reservation',
                'eid' => $reservation['id'] ?? null,
                'lid' => $reservation['lab_id'] ?? 1,
                'act' => 'Reservation updated to Approved'
            ],
            [
                'event' => 'Announcement created',
                'desc' => "Admin created announcement: \"Final Exam Schedule\"",
                'type' => 'announcement',
                'eid' => $announcement['id'] ?? '1',
                'lid' => null,
                'act' => 'Created announcement "Final Exam Schedule"'
            ],
            [
                'event' => 'Sessions reset',
                'desc' => "Admin performed bulk session reset to 30 for 150 active students.",
                'type' => 'system',
                'eid' => 'bulk_reset',
                'lid' => null,
                'act' => 'Reset sessions for 150 students'
            ]
        ];

        $stmt = $this->db->prepare("
            INSERT INTO admin_audit_log 
                (event_type, actor_id, actor_role, description, entity_type, entity_id, lab_id, action)
            VALUES 
                (:event_type, 'admin', 'admin', :description, :entity_type, :entity_id, :lab_id, :action)
        ");

        foreach ($logs as $l) {
            $stmt->execute([
                ':event_type'  => $l['event'],
                ':description' => $l['desc'],
                ':entity_type' => $l['type'],
                ':entity_id'   => (string)$l['eid'],
                ':lab_id'      => $l['lid'],
                ':action'      => $l['act']
            ]);
        }

        echo "  → " . count($logs) . " system audit logs seeded.\n";
    }
}
