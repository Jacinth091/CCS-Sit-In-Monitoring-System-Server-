<?php

class ReservationSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        $students = $this->db->query("SELECT student_id FROM students LIMIT 5")
                             ->fetchAll(PDO::FETCH_COLUMN);

        $labs = $this->db->query("SELECT id FROM laboratories WHERE is_available = true LIMIT 3")
                         ->fetchAll(PDO::FETCH_COLUMN);

        if (empty($students) || empty($labs)) {
            echo "  → Skipped: Run StudentSeeder and LaboratorySeeder first.\n";
            return;
        }

        $reservations = [
            [
                'student_id'    => $students[0],
                'lab_id'        => $labs[0],
                'purpose'       => 'Thesis Defense Preparation',
                'reserved_date' => '2025-03-25',
                'reserved_time' => '08:00:00',
                'status'        => 'approved',
            ],
            [
                'student_id'    => $students[1],
                'lab_id'        => $labs[1],
                'purpose'       => 'Web Development Project',
                'reserved_date' => '2025-03-25',
                'reserved_time' => '10:00:00',
                'status'        => 'pending',
            ],
            [
                'student_id'    => $students[2],
                'lab_id'        => $labs[0],
                'purpose'       => 'Database Finals Review',
                'reserved_date' => '2025-03-26',
                'reserved_time' => '13:00:00',
                'status'        => 'pending',
            ],
            [
                'student_id'    => $students[3],
                'lab_id'        => $labs[2],
                'purpose'       => 'Java Programming Activity',
                'reserved_date' => '2025-03-27',
                'reserved_time' => '09:00:00',
                'status'        => 'rejected',
            ],
            [
                'student_id'    => $students[4],
                'lab_id'        => $labs[1],
                'purpose'       => 'Research Paper Writing',
                'reserved_date' => '2025-03-28',
                'reserved_time' => '14:00:00',
                'status'        => 'approved',
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO reservations (student_id, lab_id, purpose, reserved_date, reserved_time, status)
            VALUES (:student_id, :lab_id, :purpose, :reserved_date, :reserved_time, :status)
        ");

        foreach ($reservations as $reservation) {
            $stmt->execute($reservation);
        }

        echo "  → " . count($reservations) . " reservations seeded.\n";
    }
}