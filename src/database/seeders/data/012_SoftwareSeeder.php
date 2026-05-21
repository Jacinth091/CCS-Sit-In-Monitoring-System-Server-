<?php

class SoftwareSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Clear existing software and mappings
        $this->db->exec("TRUNCATE TABLE software RESTART IDENTITY CASCADE");
        $this->db->exec("TRUNCATE TABLE lab_software RESTART IDENTITY CASCADE");

        $softwareList = [
            [
                'name' => 'Visual Studio Code',
                'description' => 'A lightweight but powerful source code editor which runs on your desktop.',
                'version' => '1.85.0',
                'labs' => ['LAB 526', 'LAB 527', 'LAB 529', 'LAB 542']
            ],
            [
                'name' => 'JetBrains IntelliJ IDEA',
                'description' => 'The leading Java and Kotlin IDE.',
                'version' => '2023.3',
                'labs' => ['LAB 526', 'LAB 529']
            ],
            [
                'name' => 'Cisco Packet Tracer',
                'description' => 'A cross-platform visual simulation tool designed by Cisco Systems.',
                'version' => '8.2.1',
                'labs' => ['LAB 525', 'LAB 540']
            ],
            [
                'name' => 'Adobe Photoshop',
                'description' => 'The world\'s best imaging and graphic design software.',
                'version' => '2024 (25.0)',
                'labs' => ['LAB 528']
            ],
            [
                'name' => 'MySQL Workbench',
                'description' => 'A unified visual tool for database architects, developers, and DBAs.',
                'version' => '8.0.34',
                'labs' => ['LAB 530', 'LAB 527']
            ],
            [
                'name' => 'Android Studio',
                'description' => 'The official Integrated Development Environment (IDE) for Android app development.',
                'version' => 'Hedgehog 2023.1.1',
                'labs' => ['LAB 542']
            ],
            [
                'name' => 'Anaconda (Python)',
                'description' => 'The world\'s most popular data science platform.',
                'version' => '2023.09',
                'labs' => ['LAB 544', 'LAB 526']
            ],
            [
                'name' => 'Wireshark',
                'description' => 'The world\'s foremost and widely-used network protocol analyzer.',
                'version' => '4.2.0',
                'labs' => ['LAB 525', 'LAB 540']
            ],
            [
                'name' => 'Docker Desktop',
                'description' => 'Collaborative framework for build, ship, and run any app, anywhere.',
                'version' => '4.26.0',
                'labs' => ['LAB 529', 'LAB 544']
            ],
            [
                'name' => 'Unity Hub & Editor',
                'description' => 'Cross-platform game engine developed by Unity Technologies.',
                'version' => '2022.3 LTS',
                'labs' => ['LAB 528']
            ],
            [
                'name' => 'Microsoft Office 365',
                'description' => 'Productivity suite including Word, Excel, PowerPoint.',
                'version' => 'v16.0',
                'labs' => ['LAB 525', 'LAB 526', 'LAB 527', 'LAB 528', 'LAB 529', 'LAB 530', 'LAB 540', 'LAB 542', 'LAB 544']
            ]
        ];

        $softStmt = $this->db->prepare("
            INSERT INTO software (name, description, version, is_active)
            VALUES (:name, :description, :version, TRUE)
            RETURNING id
        ");

        $labMapStmt = $this->db->prepare("
            INSERT INTO lab_software (lab_id, software_id)
            SELECT id, :software_id FROM laboratories WHERE lab_code = :lab_code
        ");

        $seededCount = 0;
        $mappedCount = 0;

        foreach ($softwareList as $sw) {
            $softStmt->execute([
                ':name' => $sw['name'],
                ':description' => $sw['description'],
                ':version' => $sw['version']
            ]);
            
            $softwareId = $softStmt->fetchColumn();
            $seededCount++;

            foreach ($sw['labs'] as $labCode) {
                $labMapStmt->execute([
                    ':software_id' => $softwareId,
                    ':lab_code' => $labCode
                ]);
                $mappedCount++;
            }
        }

        echo "  → $seededCount software items seeded.\n";
        echo "  → $mappedCount lab-software mappings created.\n";

        // Seed some student software requests
        $this->db->exec("TRUNCATE TABLE student_software_requests RESTART IDENTITY CASCADE");

        $requests = [
            [
                'student_id' => '23784994',
                'lab_code' => 'LAB 526',
                'software_name' => 'Visual Studio 2022',
                'reason' => 'Required for our .NET development project.',
                'status' => 'pending'
            ],
            [
                'student_id' => '23748985',
                'lab_code' => 'LAB 525',
                'software_name' => 'GNS3',
                'reason' => 'Need a more advanced network simulator than Packet Tracer.',
                'status' => 'reviewed'
            ],
            [
                'student_id' => '23748986',
                'lab_code' => 'LAB 528',
                'software_name' => 'Blender',
                'reason' => 'For our 3D animation class projects.',
                'status' => 'pending'
            ],
            [
                'student_id' => '23784994',
                'lab_code' => 'LAB 542',
                'software_name' => 'Flutter SDK',
                'reason' => 'Needed for personal project development.',
                'status' => 'pending'
            ]
        ];

        $reqStmt = $this->db->prepare("
            INSERT INTO student_software_requests (student_id, lab_id, software_name, reason, status)
            SELECT :student_id, id, :software_name, :reason, :status FROM laboratories WHERE lab_code = :lab_code
        ");

        foreach ($requests as $req) {
            $reqStmt->execute([
                ':student_id' => $req['student_id'],
                ':lab_code' => $req['lab_code'],
                ':software_name' => $req['software_name'],
                ':reason' => $req['reason'],
                ':status' => $req['status']
            ]);
        }

        echo "  → " . count($requests) . " student software requests seeded.\n";
    }
}
