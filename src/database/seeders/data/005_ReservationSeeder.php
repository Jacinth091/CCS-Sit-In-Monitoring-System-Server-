<?php

class ReservationSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Clear existing reservations
        $this->db->exec("TRUNCATE TABLE reservations RESTART IDENTITY CASCADE");

        $students = $this->db->query("SELECT student_id FROM students")->fetchAll(PDO::FETCH_COLUMN);
        $labs = $this->db->query("SELECT id, capacity FROM laboratories WHERE is_active = true")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students) || empty($labs)) {
            echo "  → Skipped: Run StudentSeeder and LaboratorySeeder first.\n";
            return;
        }

        $purposes = [
            'Thesis Defense Preparation',
            'Web Development Project',
            'Database Finals Review',
            'Java Programming Activity',
            'Research Paper Writing',
            'Capstone Project Work',
            'Study Group Session',
            'System Architecture Design'
        ];

        $statuses = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];
        $times = ['08:00:00', '09:00:00', '10:00:00', '11:00:00', '13:00:00', '14:00:00', '15:00:00', '16:00:00'];

        $stmt = $this->db->prepare("
            INSERT INTO reservations (student_id, lab_id, pc_number, purpose, reserved_date, reserved_time, status, admin_note)
            VALUES (:student_id, :lab_id, :pc_number, :purpose, :reserved_date, :reserved_time, :status, :admin_note)
        ");

        $now = new DateTime();
        $count = 0;

        // Generate 100 reservations
        for ($i = 0; $i < 100; $i++) {
            $student_id = $students[array_rand($students)];
            $lab = $labs[array_rand($labs)];
            $lab_id = $lab['id'];
            $pc_number = rand(1, $lab['capacity']);
            $purpose = $purposes[array_rand($purposes)];
            $reserved_time = $times[array_rand($times)];
            
            // Dates from 7 days ago to 14 days in future
            $days_offset = rand(-7, 14);
            $reserved_date = (clone $now)->modify("$days_offset days")->format('Y-m-d');
            
            $status = $statuses[array_rand($statuses)];
            
            // If date is in future, it shouldn't be 'completed'
            if ($days_offset > 0 && $status === 'completed') {
                $status = 'approved';
            }
            // If date is in past, it shouldn't be 'pending'
            if ($days_offset < 0 && $status === 'pending') {
                $status = 'completed';
            }

            $admin_note = null;
            if ($status === 'rejected') {
                $admin_note = 'Lab already booked for maintenance.';
            } elseif ($status === 'approved' && rand(1, 10) > 8) {
                $admin_note = 'Please proceed to the assigned PC.';
            }

            $stmt->execute([
                ':student_id'    => $student_id,
                ':lab_id'        => $lab_id,
                ':pc_number'     => $pc_number,
                ':purpose'       => $purpose,
                ':reserved_date' => $reserved_date,
                ':reserved_time' => $reserved_time,
                ':status'        => $status,
                ':admin_note'    => $admin_note
            ]);
            $count++;
        }

        echo "  → " . $count . " reservations seeded with varied schedules.\n";
    }
}
