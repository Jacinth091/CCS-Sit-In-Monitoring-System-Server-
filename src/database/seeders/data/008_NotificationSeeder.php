<?php

class NotificationSeeder {
    private $db;
    private $adminUsername;

    public function __construct($db) { 
        $this->db = $db; 
        $this->adminUsername = $_ENV['ADMIN_USERNAME'] ?? 'admin';
    }

    public function run() {
        // We need students and sit-in logs to create relevant notifications
        $students = $this->db->query("SELECT student_id FROM students LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
        $log = $this->db->query("SELECT id FROM sit_in_logs WHERE status = 'completed' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $announcement = $this->db->query("SELECT id FROM announcements LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if (empty($students)) {
            echo "  → Skipped: No students found to create notifications for.
";
            return;
        }

        $notifications = [
            [
                'student_id'     => $students[0],
                'admin_username' => $this->adminUsername,
                'type'           => 'system',
                'title'          => 'Password Reset Successful',
                'message'        => 'Your password has been successfully reset. If you did not initiate this change, please contact support immediately.',
                'reference_id'   => null,
                'reference_type' => null,
            ],
            [
                'student_id'     => $students[1],
                'admin_username' => $this->adminUsername,
                'type'           => 'feedback',
                'title'          => 'Feedback on Recent Sit-In',
                'message'        => 'An admin has left feedback on your recent sit-in session.',
                'reference_id'   => $log ? $log['id'] : null, // Use a real log ID if available
                'reference_type' => $log ? 'sit_in_log' : null,
            ],
            [
                'student_id'     => $students[2],
                'admin_username' => null,
                'type'           => 'announcement',
                'title'          => 'New Announcement Published',
                'message'        => 'A new announcement has been posted. Please check the announcements page for details.',
                'reference_id'   => $announcement ? $announcement['id'] : null,
                'reference_type' => $announcement ? 'announcement' : null,
            ],
            [
                'student_id'     => $students[3],
                'admin_username' => $this->adminUsername,
                'type'           => 'sit_in',
                'title'          => 'Sit-in Session Approved',
                'message'        => 'Your reservation for the Multimedia Lab on April 20 has been approved.',
                'reference_id'   => null,
                'reference_type' => 'reservation',
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO notifications (student_id, admin_username, type, title, message, reference_id, reference_type)
            VALUES (:student_id, :admin_username, :type, :title, :message, :reference_id, :reference_type)
        ");

        $count = 0;
        foreach ($notifications as $notification) {
            // Don't insert notifications that couldn't find a reference
            if ($notification['type'] !== 'system' && $notification['reference_id'] === null) {
                continue;
            }
            $stmt->execute($notification);
            $count++;
        }

        echo "  → $count notifications seeded.
";
    }
}
