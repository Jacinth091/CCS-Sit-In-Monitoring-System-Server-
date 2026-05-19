<?php

class PCSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        // Clear existing PCs for fresh seed
        $this->db->exec("TRUNCATE TABLE pcs RESTART IDENTITY CASCADE");

        // Fetch all labs
        $labs = $this->db->query("SELECT id, capacity FROM laboratories")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($labs)) {
            echo "  → Skipped: Run LaboratorySeeder first.\n";
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO pcs (lab_id, pc_number, pc_status, reservation_status, notes, updated_at)
            VALUES (:lab_id, :pc_number, :pc_status, :reservation_status, :notes, NOW())
        ");

        $count = 0;
        foreach ($labs as $lab) {
            $capacity = (int)$lab['capacity'];
            for ($i = 1; $i <= $capacity; $i++) {
                $status = 'active';
                $res_status = 'open';
                $notes = null;

                // Randomly set some PCs to disabled or under maintenance
                $rand = rand(1, 100);
                if ($rand <= 5) {
                    $status = 'disabled';
                    $res_status = 'unavailable';
                    $notes = 'Hardware failure';
                } elseif ($rand <= 10) {
                    $status = 'under maintenance';
                    $res_status = 'unavailable';
                    $notes = 'OS Update in progress';
                } elseif ($rand <= 15) {
                    $res_status = 'reserved';
                } elseif ($rand <= 20) {
                    $res_status = 'occupied';
                }

                $stmt->execute([
                    ':lab_id' => $lab['id'],
                    ':pc_number' => $i,
                    ':pc_status' => $status,
                    ':reservation_status' => $res_status,
                    ':notes' => $notes
                ]);
                $count++;
            }
        }

        echo "  → " . $count . " PCs seeded with varied statuses.\n";
    }
}
