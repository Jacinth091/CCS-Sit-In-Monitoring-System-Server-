<?php 

    class Student{

        private $conn;
        private $table = 'students';


        // Properties
        public $id;
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

        public function read(){
            $query = 'SELECT 
                id,
                student_id,
                first_name,
                last_name,
                middle_name,
                course_level,
                email,
                session,
                course,
                address,
                profile_pic,
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
                (student_id, first_name, last_name, middle_name, course, course_level, email, password, address) 
                VALUES 
                (:student_id, :first_name, :last_name, :middle_name, :course, :course_level, :email, :password, :address)
                RETURNING id';

            $stmt = $this->conn->prepare($query);

            $this->student_id   = htmlspecialchars(strip_tags($this->student_id));
            $this->first_name   = htmlspecialchars(strip_tags($this->first_name));
            $this->last_name    = htmlspecialchars(strip_tags($this->last_name));
            $this->middle_name  = htmlspecialchars(strip_tags($this->middle_name));
            $this->course       = htmlspecialchars(strip_tags($this->course));
            $this->course_level = htmlspecialchars(strip_tags($this->course_level));
            $this->email        = htmlspecialchars(strip_tags($this->email));
            $this->address      = htmlspecialchars(strip_tags($this->address ?? '')); // ← add this

            $stmt->bindParam(':student_id',   $this->student_id);
            $stmt->bindParam(':first_name',   $this->first_name);
            $stmt->bindParam(':last_name',    $this->last_name);
            $stmt->bindParam(':middle_name',  $this->middle_name);
            $stmt->bindParam(':course',       $this->course);
            $stmt->bindParam(':course_level', $this->course_level);
            $stmt->bindParam(':email',        $this->email);
            $stmt->bindParam(':password',     $this->password);
            $stmt->bindParam(':address',      $this->address);  // ← add this

            try {
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $row['id'];
                return $this->id;
            } catch(PDOException $e) {
                echo json_encode(['message' => $e->getMessage()]);
                return false;
            }
        }
        // GET Student by Student Id
        public function login(){
            $query = 'SELECT id, student_id, first_name, last_name, middle_name, email, password, is_active, course, course_level, address, session, profile_pic
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
        public function read_single() {
            $query = 'SELECT 
                        id, first_name, last_name, middle_name,
                        course_level, email, session, course,
                        address, profile_pic, is_active, created_at
                    FROM ' . $this->table . '
                    WHERE id = :id
                    LIMIT 1';

            $stmt = $this->conn->prepare($query);

            $this->id = htmlspecialchars(strip_tags($this->id));
            $stmt->bindParam(':id', $this->id);

            try {
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo json_encode(['message' => $e->getMessage()]);
                return false;
            }
        }

    // UPDATE student profile
    public function update() {
        $query = 'UPDATE ' . $this->table . ' SET
                    first_name   = :first_name,
                    last_name    = :last_name,
                    middle_name  = :middle_name,
                    course       = :course,
                    course_level = :course_level,
                    email        = :email,
                    address      = :address
                WHERE id = :id';

        $stmt = $this->conn->prepare($query);

        $this->first_name   = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name    = htmlspecialchars(strip_tags($this->last_name));
        $this->middle_name  = htmlspecialchars(strip_tags($this->middle_name));
        $this->course       = htmlspecialchars(strip_tags($this->course));
        $this->course_level = htmlspecialchars(strip_tags($this->course_level));
        $this->email        = htmlspecialchars(strip_tags($this->email));
        $this->address      = htmlspecialchars(strip_tags($this->address));
        $this->id           = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':first_name',   $this->first_name);
        $stmt->bindParam(':last_name',    $this->last_name);
        $stmt->bindParam(':middle_name',  $this->middle_name);
        $stmt->bindParam(':course',       $this->course);
        $stmt->bindParam(':course_level', $this->course_level);
        $stmt->bindParam(':email',        $this->email);
        $stmt->bindParam(':address',      $this->address);
        $stmt->bindParam(':id',           $this->id);

        try {
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            echo json_encode(['message' => $e->getMessage()]);
            return false;
        }
    }

    // SEARCH students by name or course
    public function search($keyword) {
        $query = 'SELECT 
                    id, student_id, first_name, last_name, middle_name,
                    course_level, email, course, session, profile_pic, is_active
                FROM ' . $this->table . '
                WHERE 
                    first_name   ILIKE :keyword OR
                    last_name    ILIKE :keyword OR
                    middle_name  ILIKE :keyword OR
                    course       ILIKE :keyword OR
                    course_level ILIKE :keyword
                ORDER BY created_at DESC';

        $stmt = $this->conn->prepare($query);

        $keyword = '%' . htmlspecialchars(strip_tags($keyword)) . '%';
        $stmt->bindParam(':keyword', $keyword);

        try {
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            echo json_encode(['message' => $e->getMessage()]);
            return false;
        }
    }

    }

?>