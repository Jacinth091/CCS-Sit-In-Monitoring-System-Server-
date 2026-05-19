<?php

class LaboratorySeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Clear existing labs for fresh seed
        $this->db->exec("TRUNCATE TABLE laboratories RESTART IDENTITY CASCADE");

        $labs = [
            [
                'name'     => 'Advanced Programming Lab',
                'lab_code' => 'LAB 526',
                'capacity' => 40,
                'is_active' => true,
                'lab_type' => 'Programming',
                'description' => 'Equipped with high-end workstations for advanced software development.'
            ],
            [
                'name'     => 'Cisco Networking Lab',
                'lab_code' => 'LAB 525',
                'capacity' => 30,
                'is_active' => true,
                'lab_type' => 'Networking',
                'description' => 'Specialized lab with Cisco routers, switches, and networking equipment.'
            ],
            [
                'name'     => 'Multimedia and Graphics Lab',
                'lab_code' => 'LAB 528',
                'capacity' => 35,
                'is_active' => true,
                'lab_type' => 'Multimedia',
                'description' => 'Optimized for graphics design, video editing, and 3D modeling.'
            ],
            [
                'name'     => 'Web Development Lab',
                'lab_code' => 'LAB 527',
                'capacity' => 40,
                'is_active' => true,
                'lab_type' => 'Web',
                'description' => 'General purpose lab focused on modern web technologies.'
            ],
            [
                'name'     => 'Software Engineering Lab',
                'lab_code' => 'LAB 529',
                'capacity' => 40,
                'is_active' => true,
                'lab_type' => 'General Purpose',
                'description' => 'Collaborative space for software design and project management.'
            ],
            [
                'name'     => 'Database Systems Lab',
                'lab_code' => 'LAB 530',
                'capacity' => 30,
                'is_active' => true,
                'lab_type' => 'Database',
                'description' => 'Dedicated to SQL, NoSQL, and big data processing.'
            ],
            [
                'name'     => 'Cybersecurity Lab',
                'lab_code' => 'LAB 540',
                'capacity' => 25,
                'is_active' => true,
                'lab_type' => 'Security',
                'description' => 'Isolated environment for security auditing and ethical hacking.'
            ],
            [
                'name'     => 'Mobile Computing Lab',
                'lab_code' => 'LAB 542',
                'capacity' => 30,
                'is_active' => true,
                'lab_type' => 'Mobile',
                'description' => 'Equipped for Android and iOS app development.'
            ],
            [
                'name'     => 'Artificial Intelligence Lab',
                'lab_code' => 'LAB 544',
                'capacity' => 20,
                'is_active' => true,
                'lab_type' => 'Research',
                'description' => 'High-performance computing for AI, ML, and data science.'
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO laboratories (name, lab_code, capacity, is_active, lab_type, description)
            VALUES (:name, :lab_code, :capacity, :is_active, :lab_type, :description)
        ");

        foreach ($labs as $lab) {
            $stmt->execute([
                ':name'        => $lab['name'],
                ':lab_code'    => $lab['lab_code'],
                ':capacity'    => $lab['capacity'],
                ':is_active'   => $lab['is_active'],
                ':lab_type'    => $lab['lab_type'],
                ':description' => $lab['description'],
            ]);
        }

        echo "  → " . count($labs) . " laboratories seeded.\n";
    }
}
