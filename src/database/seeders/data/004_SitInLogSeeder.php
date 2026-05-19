<?php

class SitInLogSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Clear existing logs for fresh seed
        $this->db->exec("TRUNCATE TABLE sit_in_logs RESTART IDENTITY CASCADE");

        // Fetch all students and labs
        $students = $this->db->query("SELECT student_id FROM students")->fetchAll(PDO::FETCH_COLUMN);
        $labs = $this->db->query("SELECT id, capacity FROM laboratories WHERE is_active = true")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students) || empty($labs)) {
            echo "  → Skipped: Run StudentSeeder and LaboratorySeeder first.\n";
            return;
        }

        $purposes = [
            'C Programming',
            'Java Programming',
            'Web Development',
            'Database Design',
            'Object Oriented Programming',
            'Networking',
            'System Architecture',
            'Mobile App Development',
            'Research',
            'Thesis Writing',
            'Practicing Skills',
            'Assignment Completion'
        ];

        $stmt = $this->db->prepare("
            INSERT INTO sit_in_logs (student_id, lab_id, pc_number, purpose, time_in, time_out, status)
            VALUES (:student_id, :lab_id, :pc_number, :purpose, :time_in, :time_out, :status)
        ");

        $updateStmt = $this->db->prepare("
            UPDATE students 
            SET session = GREATEST(0, session - 1)
            WHERE student_id = :student_id
        ");

        $count = 0;
        $now = new DateTime();
        
        // Generate ~250 logs spread over the last 30 days
        for ($i = 0; $i < 250; $i++) {
            $student_id = $students[array_rand($students)];
            $lab = $labs[array_rand($labs)];
            $lab_id = $lab['id'];
            $pc_number = (string)rand(1, $lab['capacity']);
            $purpose = $purposes[array_rand($purposes)];
            
            // Random date within last 30 days
            $days_ago = rand(0, 30);
            $hour = rand(7, 18); // Lab hours: 7 AM to 6 PM
            $minute = rand(0, 59);
            
            $time_in = (clone $now)->modify("-$days_ago days");
            $time_in->setTime($hour, $minute, 0);
            
            // Decide status: mostly completed, some ongoing if date is today
            $status = 'completed';
            $time_out = null;
            
            // If it's today and within the last 3 hours, maybe it's still ongoing
            if ($days_ago === 0 && $time_in > (clone $now)->modify('-3 hours') && rand(1, 10) > 7) {
                $status = 'ongoing';
            } else {
                // Average session 30 mins to 4 hours
                $duration_minutes = rand(30, 240);
                $time_out_obj = (clone $time_in)->modify("+$duration_minutes minutes");
                $time_out = $time_out_obj->format('Y-m-d H:i:s');
            }

            $stmt->execute([
                ':student_id' => $student_id,
                ':lab_id'     => $lab_id,
                ':pc_number'  => $pc_number,
                ':purpose'    => $purpose,
                ':time_in'    => $time_in->format('Y-m-d H:i:s'),
                ':time_out'   => $time_out,
                ':status'     => $status
            ]);

            if ($status === 'completed') {
                $updateStmt->execute([':student_id' => $student_id]);
            }
            $count++;
        }

        echo "  → " . $count . " realistic sit-in logs seeded for analytics.\n";
    }
}
