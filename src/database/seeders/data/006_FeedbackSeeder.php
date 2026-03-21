<?php

class FeedbackSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Only seed feedback on completed sit-in logs
        $logs = $this->db->query("
            SELECT id, student_id FROM sit_in_logs 
            WHERE status = 'completed' 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($logs)) {
            echo "  → Skipped: Run SitInLogSeeder first.\n";
            return;
        }

        $feedbacks = [
            [
                'rating'  => 5,
                'comment' => 'The laboratory is well-maintained and the equipment is in good condition.',
            ],
            [
                'rating'  => 4,
                'comment' => 'Good environment for studying. Some computers were a bit slow.',
            ],
            [
                'rating'  => 5,
                'comment' => 'Very comfortable and quiet. Great for focused work.',
            ],
            [
                'rating'  => 3,
                'comment' => 'The lab was a bit crowded. Otherwise it was fine.',
            ],
            [
                'rating'  => 4,
                'comment' => 'Clean and organized. The internet connection could be faster.',
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO feedback (student_id, sit_in_id, rating, comment)
            VALUES (:student_id, :sit_in_id, :rating, :comment)
        ");

        $count = 0;
        foreach ($logs as $index => $log) {
            if (!isset($feedbacks[$index])) break;

            $stmt->execute([
                'student_id' => $log['student_id'],
                'sit_in_id'  => $log['id'],
                'rating'     => $feedbacks[$index]['rating'],
                'comment'    => $feedbacks[$index]['comment'],
            ]);

            $count++;
        }

        echo "  → $count feedback entries seeded.\n";
    }
}