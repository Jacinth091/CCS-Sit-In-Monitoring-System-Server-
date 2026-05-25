<?php

class TestimonialSeeder {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function run() {
        // Truncate existing testimonials
        $this->db->exec("TRUNCATE TABLE testimonials RESTART IDENTITY CASCADE");

        // Get some students
        $stmt = $this->db->query("SELECT student_id FROM students LIMIT 5");
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($students)) {
            echo "No students found to seed testimonials.\n";
            return;
        }

        $testimonials = [
            [
                'content' => 'Great environment for studying! The PCs are fast and the internet is stable.',
                'rating' => 5,
                'is_approved' => true,
                'is_anonymous' => false
            ],
            [
                'content' => 'The lab assistants are very helpful. I love the new reservation system.',
                'rating' => 5,
                'is_approved' => true,
                'is_anonymous' => true
            ],
            [
                'content' => 'Sometimes it gets a bit loud, but overall a good place to work.',
                'rating' => 4,
                'is_approved' => true,
                'is_anonymous' => false
            ],
            [
                'content' => 'Highly recommended! The facilities are top-notch.',
                'rating' => 5,
                'is_approved' => false,
                'is_anonymous' => true
            ],
            [
                'content' => 'I wish there were more outlets for laptops, but the PCs are great.',
                'rating' => 4,
                'is_approved' => false,
                'is_anonymous' => false
            ]
        ];

        foreach ($testimonials as $index => $t) {
            $student_id = $students[$index % count($students)];
            $stmt = $this->db->prepare("
                INSERT INTO testimonials (student_id, content, rating, is_approved, is_anonymous) 
                VALUES (:student_id, :content, :rating, :is_approved, :is_anonymous)
            ");
            $stmt->execute([
                ':student_id' => $student_id,
                ':content' => $t['content'],
                ':rating' => $t['rating'],
                ':is_approved' => $t['is_approved'] ? 'true' : 'false',
                ':is_anonymous' => $t['is_anonymous'] ? 'true' : 'false'
            ]);
        }

        echo "Seeded " . count($testimonials) . " testimonials.\n";
    }
}
