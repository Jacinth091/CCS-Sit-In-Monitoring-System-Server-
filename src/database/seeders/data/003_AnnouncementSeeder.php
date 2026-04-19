<?php

class AnnouncementSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Clear existing announcements for fresh seed
        $this->db->exec("TRUNCATE TABLE announcements RESTART IDENTITY CASCADE");

        $now = new DateTime();
        
        $announcements = [
            [
                'title'   => '⚠️ URGENT: Laboratory Power Interruption',
                'content' => 'Please be advised that there will be a scheduled power interruption affecting all CCS Laboratories tomorrow from 8:00 AM to 12:00 PM for maintenance. All ongoing sessions must be saved and logged out before 8:00 AM.',
                'status'  => 'published',
                'is_pinned' => true,
                'is_important' => true,
                'admin_username' => 'admin',
                'created_at' => (clone $now)->modify('-1 hour')->format('Y-m-d H:i:s')
            ],
            [
                'title'   => 'Welcome to CCS Sit-In Monitoring System',
                'content' => 'Welcome to the new semester! Students may now use the system to log their sit-in sessions in the CCS laboratories. Please ensure you log your time-out before leaving the lab to accurately track your remaining sessions.',
                'status'  => 'published',
                'is_pinned' => true,
                'is_important' => false,
                'admin_username' => 'admin',
                'created_at' => (clone $now)->modify('-10 days')->format('Y-m-d H:i:s')
            ],
            [
                'title'   => 'Laboratory Rules and Regulations (REVISED)',
                'content' => "Please observe proper conduct inside the laboratory at all times:\n1. No food or drinks allowed.\n2. Maintain silence and order.\n3. Games are strictly prohibited.\n4. Always log your time-in and time-out.\n\nFailure to follow these rules may result in suspension of lab privileges.",
                'status'  => 'published',
                'is_pinned' => false,
                'is_important' => true,
                'admin_username' => 'admin',
                'created_at' => (clone $now)->modify('-5 days')->format('Y-m-d H:i:s')
            ],
            [
                'title'   => 'Network Lab Maintenance Extension',
                'content' => 'The Network Lab maintenance has been extended until the end of this week. Students who have scheduled activities in the Network Lab are advised to coordinate with their instructors for alternative arrangements.',
                'status'  => 'published',
                'is_pinned' => false,
                'is_important' => true,
                'admin_username' => 'admin',
                'created_at' => (clone $now)->modify('-2 days')->format('Y-m-d H:i:s')
            ],
            [
                'title'   => 'Upcoming Programming Competition',
                'content' => 'The CCS will be hosting a programming competition on the 30th of this month. Registration is now open at the Dean\'s office. Exciting prizes await the winners!',
                'status'  => 'published',
                'is_pinned' => false,
                'is_important' => false,
                'admin_username' => 'admin',
                'created_at' => (clone $now)->modify('-1 day')->format('Y-m-d H:i:s')
            ],
            [
                'title'   => 'New Lab Assistant Positions',
                'content' => 'We are looking for student lab assistants for the evening shift. Interested applicants may submit their resume and class schedule to the lab supervisor.',
                'status'  => 'published',
                'is_pinned' => false,
                'is_important' => false,
                'admin_username' => 'admin',
                'created_at' => (clone $now)->modify('-4 hours')->format('Y-m-d H:i:s')
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO announcements (title, content, status, is_pinned, is_important, admin_username, created_at)
            VALUES (:title, :content, :status, :is_pinned, :is_important, :admin_username, :created_at)
        ");

        foreach ($announcements as $ann) {
            $stmt->execute([
                ':title'          => $ann['title'],
                ':content'        => $ann['content'],
                ':status'         => $ann['status'],
                ':is_pinned'      => $ann['is_pinned'] ? 1 : 0,
                ':is_important'   => $ann['is_important'] ? 1 : 0,
                ':admin_username' => $ann['admin_username'],
                ':created_at'     => $ann['created_at']
            ]);
        }

        echo "  → " . count($announcements) . " announcements seeded.\n";
    }
}