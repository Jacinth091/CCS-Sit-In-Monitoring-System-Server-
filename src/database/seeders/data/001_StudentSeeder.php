<?php

class StudentSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        $students = [
            [
                'student_id'   => '23748985',
                'first_name'   => 'Juan',
                'last_name'    => 'Dela Cruz',
                'middle_name'  => 'Santos',
                'course'       => 'BSIT',
                'course_level' => '3rd Year',
                'email'        => 'juan.delacruz@ccs.edu',
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748986',
                'first_name'   => 'Maria',
                'last_name'    => 'Santos',
                'middle_name'  => 'Cruz',
                'course'       => 'BSCS',
                'course_level' => '2nd Year',
                'email'        => 'maria.santos@ccs.edu',
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748987',
                'first_name'   => 'Pedro',
                'last_name'    => 'Reyes',
                'middle_name'  => 'Lim',
                'course'       => 'BSIT',
                'course_level' => '1st Year',
                'email'        => 'pedro.reyes@ccs.edu',
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748988',
                'first_name'   => 'Ana',
                'last_name'    => 'Garcia',
                'middle_name'  => 'Rivera',
                'course'       => 'BSCS',
                'course_level' => '4th Year',
                'email'        => 'ana.garcia@ccs.edu',
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748989',
                'first_name'   => 'Carlo',
                'last_name'    => 'Mendoza',
                'middle_name'  => 'Bautista',
                'course'       => 'BSIT',
                'course_level' => '1st Year',
                'email'        => 'carlo.mendoza@ccs.edu',
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748990',
                'first_name'   => 'Lisa',
                'last_name'    => 'Flores',
                'middle_name'  => 'Tan',
                'course'       => 'BSCS',
                'course_level' => '2nd Year',
                'email'        => 'lisa.flores@ccs.edu',
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748991',
                'first_name'   => 'Marco',
                'last_name'    => 'Torres',
                'middle_name'  => 'Villanueva',
                'course'       => 'BSIT',
                'course_level' => '3rd Year',
                'email'        => 'marco.torres@ccs.edu',
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748992',
                'first_name'   => 'Sofia',
                'last_name'    => 'Ramos',
                'middle_name'  => 'Aquino',
                'course'       => 'BSCS',
                'course_level' => '4th Year',
                'email'        => 'sofia.ramos@ccs.edu',
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO students 
                (student_id, first_name, last_name, middle_name, course, course_level, email, session, password)
            VALUES 
                (:student_id, :first_name, :last_name, :middle_name, :course, :course_level, :email, :session, :password)
            ON CONFLICT (student_id) DO NOTHING
        ");

        foreach ($students as $student) {
            $stmt->execute($student);
        }

        echo "  → " . count($students) . " students seeded.\n";
    }
}