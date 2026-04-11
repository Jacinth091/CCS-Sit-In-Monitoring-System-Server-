<?php

class AdminFeedbackSeeder {
    private $db;
    private $adminUsername;

    public function __construct($db) { 
        $this->db = $db; 
        $this->adminUsername = $_ENV['ADMIN_USERNAME'] ?? 'admin';
    }

    public function run() {
        // Grab a few completed sit-in logs that don't have feedback yet
        $logs = $this->db->query("
            SELECT sl.id FROM sit_in_logs sl
            LEFT JOIN admin_feedback af ON sl.id = af.sit_in_id
            WHERE sl.status = 'completed' AND af.id IS NULL
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($logs)) {
            echo "  → Skipped: No completed sit-in logs found without admin feedback.
";
            return;
        }

        $feedbacks = [
            'Good job cleaning up your station before leaving.',
            'Please remember to push in your chair next time. Otherwise, no issues.',
            'Session completed successfully. No issues noted.',
        ];

        $stmt = $this->db->prepare("
            INSERT INTO admin_feedback (sit_in_id, admin_username, feedback_text)
            VALUES (:sit_in_id, :admin_username, :feedback_text)
        ");

        $count = 0;
        foreach ($logs as $index => $log) {
            if (!isset($feedbacks[$index])) break;

            $stmt->execute([
                ':sit_in_id'     => $log['id'],
                ':admin_username' => $this->adminUsername,
                ':feedback_text' => $feedbacks[$index],
            ]);

            $count++;
        }

        echo "  → $count admin feedback entries seeded.
";
    }
}
