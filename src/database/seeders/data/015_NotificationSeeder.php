<?php

class NotificationSeeder {
    private $db;
    private $adminUsername;

    public function __construct($db) { 
        $this->db = $db; 
        $this->adminUsername = $_ENV['ADMIN_USERNAME'] ?? 'admin';
    }
    public function run() {
        // Clear existing notifications
        $this->db->exec("TRUNCATE TABLE notifications RESTART IDENTITY CASCADE");

        // We need students, sit-in logs, announcements, reservations, software requests, and testimonials
        $students = $this->db->query("SELECT student_id FROM students LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
        $log = $this->db->query("SELECT id FROM sit_in_logs WHERE status = 'completed' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $announcement = $this->db->query("SELECT id FROM announcements LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $reservation = $this->db->query("SELECT id FROM reservations LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $softwareRequest = $this->db->query("SELECT id FROM student_software_requests LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $testimonial = $this->db->query("SELECT id FROM testimonials LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if (empty($students)) {
            echo "  → Skipped: No students found to create notifications for.\n";
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
                'reference_id'   => $log ? (string)$log['id'] : null,
                'reference_type' => $log ? 'sit_in_log' : null,
            ],
            [
                'student_id'     => $students[2],
                'admin_username' => null,
                'type'           => 'announcement',
                'title'          => 'New Announcement Published',
                'message'        => 'A new announcement has been posted. Please check the announcements page for details.',
                'reference_id'   => $announcement ? (string)$announcement['id'] : null,
                'reference_type' => $announcement ? 'announcement' : null,
            ],
            [
                'student_id'     => $students[3] ?? $students[0],
                'admin_username' => $this->adminUsername,
                'type'           => 'reservation',
                'title'          => 'Reservation Approved',
                'message'        => 'Your reservation for LAB 526 has been approved by the admin.',
                'reference_id'   => $reservation ? (string)$reservation['id'] : null,
                'reference_type' => 'reservation',
            ],
            [
                'student_id'     => $students[4] ?? $students[0],
                'admin_username' => $this->adminUsername,
                'type'           => 'software',
                'title'          => 'Software Request Reviewed',
                'message'        => 'Your request for Visual Studio 2022 has been reviewed and approved.',
                'reference_id'   => $softwareRequest ? (string)$softwareRequest['id'] : null,
                'reference_type' => 'software_request',
            ],
            [
                'student_id'     => $students[0],
                'admin_username' => $this->adminUsername,
                'type'           => 'testimonial',
                'title'          => 'Testimonial Featured',
                'message'        => 'Your testimonial has been approved and is now featured on the system homepage.',
                'reference_id'   => $testimonial ? (string)$testimonial['id'] : null,
                'reference_type' => 'testimonial',
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO notifications (student_id, admin_username, type, title, message, reference_id, reference_type)
            VALUES (:student_id, :admin_username, :type, :title, :message, :reference_id, :reference_type)
        ");

        $count = 0;
        foreach ($notifications as $notification) {
            // Don't insert notifications that couldn't find a reference unless it's system type
            if ($notification['type'] !== 'system' && $notification['reference_id'] === null) {
                continue;
            }
            $stmt->execute($notification);
            $count++;
        }

        echo "  → $count notifications seeded.\n";
    }
}
