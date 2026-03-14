<?php 

    class Student{

        private $conn;
        private $table = 'students';


        // Properties
        public $student_id;
        public $first_name;
        public $last_name;
        public $middle_name;
        public $course_level;
        public $password;
        public $email;
        public $session;
        public $course;
        public $address;
        public $is_active;
        public $deleted_at;
        public $created_at;


        // Dependency Injection of the Database Connection
        public function __construct($db){
            $this->conn = $db;
        }

        // GET All Users
        public function read(){
            $query = 'SELECT 
                student_id,
                first_name,
                last_name,
                middle_name,
                course_level,
                email,
                session,
                course,
                address,
                is_active,
                created_at,
                deleted_at
                FROM
                '. $this->table .'
                ORDER BY created_at DESC';

            //prepare statement
            $stmt = $this->conn->prepare($query);
            //execute query

            $stmt->execute();
            return $stmt;

        }

        public function create(){

            $query = 'INSERT INTO ' . $this->table . ' 
                (student_id, first_name, last_name, middle_name, course, course_level, email, password) 
                VALUES 
                (:student_id, :first_name, :last_name, :middle_name, :course, :course_level, :email, :password)';

            $stmt = $this->conn->prepare($query);

            $this->student_id = htmlspecialchars(strip_tags($this->student_id));
            $this->first_name = htmlspecialchars(strip_tags($this->first_name));
            $this->last_name = htmlspecialchars(strip_tags($this->last_name));
            $this->middle_name = htmlspecialchars(strip_tags($this->middle_name));
            $this->course = htmlspecialchars(strip_tags($this->course));
            $this->course_level = htmlspecialchars(strip_tags($this->course_level));
            $this->email = htmlspecialchars(strip_tags($this->email));

            $stmt->bindParam(':student_id', $this->student_id);
            $stmt->bindParam(':first_name', $this->first_name);
            $stmt->bindParam(':last_name', $this->last_name);
            $stmt->bindParam(':middle_name', $this->middle_name);
            $stmt->bindParam(':course', $this->course);
            $stmt->bindParam(':course_level', $this->course_level);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $this->password);

            try {
                $stmt->execute();
                return true;
            } catch(PDOException $e) {
                echo json_encode(array('message' => $e->getMessage()));
                return false;
            }
        }
        // GET Student by Email
        public function login(){
            $query = 'SELECT student_id, first_name, last_name, email, password, is_active
                    FROM ' . $this->table . ' 
                    WHERE student_id = :student_id
                    LIMIT 1';

            $stmt = $this->conn->prepare($query);

            $this->student_id = htmlspecialchars(strip_tags($this->student_id));
            $stmt->bindParam(':student_id', $this->student_id);

            try {
                $stmt->execute();
                return $stmt->fetch();
            } catch(PDOException $e) {
                echo json_encode(array('message' => $e->getMessage()));
                return false;
            }
        }

    }

?>