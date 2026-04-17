<?php

class SitInLogSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Fetch real student_ids and lab ids from DB
        $students = $this->db->query("SELECT student_id FROM students LIMIT 6")
                             ->fetchAll(PDO::FETCH_COLUMN);

        $labs = $this->db->query("SELECT id FROM laboratories WHERE is_available = true LIMIT 3")
                         ->fetchAll(PDO::FETCH_COLUMN);

        if (empty($students) || empty($labs)) {
            echo "  → Skipped: Run StudentSeeder and LaboratorySeeder first.\n";
            return;
        }

        $purposes = [
            'C Programming',
            'Web Development',
            'Database Activity',
            'Research',
            'Java Programming',
            'Python Activity',
            'Thesis Work',
            'Network Configuration',
        ];

        $logs = [
            [
                'student_id' => $students[0],
                'lab_id'     => $labs[0],
                'purpose'    => $purposes[0],
                'time_in'    => '2025-03-10 08:00:00',
                'time_out'   => '2025-03-10 10:00:00',
                'status'     => 'completed',
            ],
            [
                'student_id' => $students[1],
                'lab_id'     => $labs[0],
                'purpose'    => $purposes[1],
                'time_in'    => '2025-03-10 09:00:00',
                'time_out'   => '2025-03-10 11:30:00',
                'status'     => 'completed',
            ],
            [
                'student_id' => $students[2],
                'lab_id'     => $labs[1],
                'purpose'    => $purposes[2],
                'time_in'    => '2025-03-11 13:00:00',
                'time_out'   => '2025-03-11 15:00:00',
                'status'     => 'completed',
            ],
            [
                'student_id' => $students[3],
                'lab_id'     => $labs[1],
                'purpose'    => $purposes[3],
                'time_in'    => '2025-03-12 10:00:00',
                'time_out'   => '2025-03-12 12:00:00',
                'status'     => 'completed',
            ],
            [
                'student_id' => $students[4],
                'lab_id'     => $labs[2],
                'purpose'    => $purposes[4],
                'time_in'    => '2025-03-13 14:00:00',
                'time_out'   => null,
                'status'     => 'ongoing',  // still inside
            ],
            [
                'student_id' => $students[0],
                'lab_id'     => $labs[2],
                'purpose'    => $purposes[5],
                'time_in'    => '2025-03-14 08:30:00',
                'time_out'   => '2025-03-14 10:30:00',
                'status'     => 'completed',
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO sit_in_logs (student_id, lab_id, purpose, time_in, time_out, status)
            VALUES (:student_id, :lab_id, :purpose, :time_in, :time_out, :status)
        ");

        $updateStmt = $this->db->prepare("
            UPDATE students 
            SET session = GREATEST(0, session - 1)
            WHERE student_id = :student_id
        ");

        foreach ($logs as $log) {
            $stmt->execute($log);
            
            // If the log is completed, decrement the student's session count
            if ($log['status'] === 'completed') {
                $updateStmt->execute([':student_id' => $log['student_id']]);
            }
        }

        echo "  → " . count($logs) . " sit-in logs seeded.\n";
    }
}