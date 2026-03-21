<?php

class AnnouncementSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        $announcements = [
            [
                'title'   => 'Welcome to CCS Sit-In Monitoring System',
                'content' => 'Students may now use the system to log their sit-in sessions in the CCS laboratories. Please ensure you log your time-out before leaving the lab.',
            ],
            [
                'title'   => 'Laboratory Rules and Regulations',
                'content' => 'Please observe proper conduct inside the laboratory at all times. No food or drinks allowed. Always log your time-in and time-out.',
            ],
            [
                'title'   => 'Network Lab Under Maintenance',
                'content' => 'The Network Lab will be unavailable from March 20 to March 27. Please use the other available laboratories for your sit-in sessions.',
            ],
            [
                'title'   => 'Reservation System Now Available',
                'content' => 'Students can now reserve laboratory slots in advance through the system. Reservations must be approved by the admin before they are confirmed.',
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO announcements (title, content)
            VALUES (:title, :content)
        ");

        foreach ($announcements as $announcement) {
            $stmt->execute($announcement);
        }

        echo "  → " . count($announcements) . " announcements seeded.\n";
    }
}