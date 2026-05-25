<?php

class LabRuleSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Truncate existing lab rules
        $this->db->exec("TRUNCATE TABLE lab_rules RESTART IDENTITY CASCADE");

        $data = [
            [
                'title' => 'Decorum & Silence',
                'description' => 'Maintain silence, decorum, and order inside the laboratory. Turn off or keep on silent mode all mobile phones.',
                'icon_name' => 'Shield',
                'display_order' => 1
            ],
            [
                'title' => 'No Gaming',
                'description' => 'Games are strictly NOT allowed inside the laboratory. This includes computer games and mobile games.',
                'icon_name' => 'Monitor',
                'display_order' => 2
            ],
            [
                'title' => 'Academic Internet Use',
                'description' => "Surfing and downloading without the instructor's permission is strictly prohibited.",
                'icon_name' => 'Target',
                'display_order' => 3
            ],
            [
                'title' => 'Food & Drinks',
                'description' => 'Food and drinks (including water) are NOT allowed inside the laboratory at any time.',
                'icon_name' => 'ShieldCheck',
                'display_order' => 4
            ],
            [
                'title' => 'Digital Log In/Out',
                'description' => 'Students must log in and out of the sit-in monitoring system when entering and leaving the lab.',
                'icon_name' => 'Info',
                'display_order' => 5
            ],
            [
                'title' => 'Issue Reporting',
                'description' => 'Report any hardware or software issues to the lab technician or instructor immediately.',
                'icon_name' => 'Shield',
                'display_order' => 6
            ],
            [
                'title' => 'Liability for Damage',
                'description' => 'Students are responsible for any damage to lab equipment caused by negligence or misuse.',
                'icon_name' => 'Target',
                'display_order' => 7
            ],
            [
                'title' => 'Scheduled Hours',
                'description' => 'Follow the scheduled lab hours. Unauthorized access outside of scheduled hours is not permitted.',
                'icon_name' => 'Clock',
                'display_order' => 8
            ]
        ];

        $stmt = $this->db->prepare("
            INSERT INTO lab_rules (title, description, icon_name, display_order)
            VALUES (:title, :description, :icon_name, :display_order)
            ON CONFLICT DO NOTHING
        ");

        foreach ($data as $item) {
            $stmt->execute($item);
        }
    }
}