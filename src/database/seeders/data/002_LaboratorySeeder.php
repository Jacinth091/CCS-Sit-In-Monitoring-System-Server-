<?php

class LaboratorySeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        $labs = [
            [
                'lab_name'     => 'CCS Lab 1',
                'capacity'     => 40,
                'is_available' => true,
            ],
            [
                'lab_name'     => 'CCS Lab 2',
                'capacity'     => 40,
                'is_available' => true,
            ],
            [
                'lab_name'     => 'CCS Lab 3',
                'capacity'     => 30,
                'is_available' => true,
            ],
            [
                'lab_name'     => 'CCS Lab 4',
                'capacity'     => 30,
                'is_available' => true,
            ],
            [
                'lab_name'     => 'MAC Lab',
                'capacity'     => 25,
                'is_available' => true,
            ],
            [
                'lab_name'     => 'Network Lab',
                'capacity'     => 20,
                'is_available' => false, 
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO laboratories (lab_name, capacity, is_available)
            VALUES (:lab_name, :capacity, :is_available)
            ON CONFLICT DO NOTHING
        ");

        foreach ($labs as $lab) {
            $stmt->bindValue(':lab_name', $lab['lab_name'], PDO::PARAM_STR);
            $stmt->bindValue(':capacity', (int) $lab['capacity'], PDO::PARAM_INT);
            $stmt->bindValue(':is_available', (bool) $lab['is_available'], PDO::PARAM_BOOL);
            $stmt->execute();
        }

        echo "  → " . count($labs) . " laboratories seeded.\n";
    }
}