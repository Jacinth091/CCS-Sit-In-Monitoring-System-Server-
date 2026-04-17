<?php

class StudentSeeder {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function run() {
        $students = [
            [
                'student_id'   => '23784994',
                'first_name'   => 'Jacinth Cedric',
                'last_name'    => 'Barral',
                'middle_name'  => 'Curitao',
                'course'       => 'Bachelor of Science in Information Technology',
                'course_level' => '3rd Year',
                'email'        => 'jacinthcedricbarral@gmail.com',
                'address'      => 'Cebu City, Philippines',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748985',
                'first_name'   => 'Juan',
                'last_name'    => 'Dela Cruz',
                'middle_name'  => 'Santos',
                'course'       => 'Bachelor of Science in Information Technology',
                'course_level' => '3rd Year',
                'email'        => 'juan.delacruz@ccs.edu',
                'address'      => 'Mandaue City, Cebu',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748986',
                'first_name'   => 'Maria',
                'last_name'    => 'Santos',
                'middle_name'  => 'Cruz',
                'course'       => 'Bachelor of Science in Computer Science',
                'course_level' => '2nd Year',
                'email'        => 'maria.santos@ccs.edu',
                'address'      => 'Lapu-Lapu City, Cebu',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748987',
                'first_name'   => 'Pedro',
                'last_name'    => 'Reyes',
                'middle_name'  => 'Lim',
                'course'       => 'Bachelor of Science in Information Technology',
                'course_level' => '1st Year',
                'email'        => 'pedro.reyes@ccs.edu',
                'address'      => 'Talamban, Cebu City',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748988',
                'first_name'   => 'Ana',
                'last_name'    => 'Garcia',
                'middle_name'  => 'Rivera',
                'course'       => 'Bachelor of Science in Computer Science',
                'course_level' => '4th Year',
                'email'        => 'ana.garcia@ccs.edu',
                'address'      => 'Guadalupe, Cebu City',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748989',
                'first_name'   => 'Carlo',
                'last_name'    => 'Mendoza',
                'middle_name'  => 'Bautista',
                'course'       => 'Bachelor of Science in Information Technology',
                'course_level' => '1st Year',
                'email'        => 'carlo.mendoza@ccs.edu',
                'address'      => 'Banilad, Cebu City',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748990',
                'first_name'   => 'Lisa',
                'last_name'    => 'Flores',
                'middle_name'  => 'Tan',
                'course'       => 'Bachelor of Science in Computer Science',
                'course_level' => '2nd Year',
                'email'        => 'lisa.flores@ccs.edu',
                'address'      => 'Pardo, Cebu City',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748991',
                'first_name'   => 'Marco',
                'last_name'    => 'Torres',
                'middle_name'  => 'Villanueva',
                'course'       => 'Bachelor of Science in Information Technology',
                'course_level' => '3rd Year',
                'email'        => 'marco.torres@ccs.edu',
                'address'      => 'Talisay City, Cebu',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
            [
                'student_id'   => '23748992',
                'first_name'   => 'Sofia',
                'last_name'    => 'Ramos',
                'middle_name'  => 'Aquino',
                'course'       => 'Bachelor of Science in Computer Science',
                'course_level' => '4th Year',
                'email'        => 'sofia.ramos@ccs.edu',
                'address'      => 'Liloan, Cebu',
                'profile_pic'  => null,
                'session'      => 30,
                'password'     => password_hash('password123', PASSWORD_DEFAULT),
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO students 
                (student_id, first_name, last_name, middle_name, course, course_level, email, address, profile_pic, session, password)
            VALUES 
                (:student_id, :first_name, :last_name, :middle_name, :course, :course_level, :email, :address, :profile_pic, :session, :password)
            ON CONFLICT (student_id) DO UPDATE SET
                first_name   = EXCLUDED.first_name,
                last_name    = EXCLUDED.last_name,
                middle_name  = EXCLUDED.middle_name,
                course       = EXCLUDED.course,
                course_level = EXCLUDED.course_level,
                email        = EXCLUDED.email,
                address      = EXCLUDED.address,
                profile_pic  = EXCLUDED.profile_pic,
                session      = EXCLUDED.session,
                password     = EXCLUDED.password
        ");

        foreach ($students as $student) {
            $stmt->execute($student);
        }

        echo "  → " . count($students) . " students seeded.\n";
    }
}