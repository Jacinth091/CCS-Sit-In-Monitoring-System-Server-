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
        public $profile_pic;
        public $is_active;
        public $deleted_at;
        public $created_at;
        public $updated_at;


        // Dependency Injection of the Database Connection
        public function __construct($db){
            $this->conn = $db;
        }

        public function read($page = null, $per_page = null, $search = null) {
            $params = [];
            $searchClause = "";
            
            if ($search !== null && trim($search) !== "") {
                $searchClause = " WHERE (student_id ILIKE :search OR first_name ILIKE :search OR last_name ILIKE :search OR middle_name ILIKE :search OR email ILIKE :search OR course ILIKE :search)";
                $params[':search'] = '%' . trim($search) . '%';
            }

            if ($page === null) {
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
                    updated_at,
                    deleted_at
                    FROM
                    '. $this->table .'
                    ' . $searchClause . '
                    ORDER BY created_at DESC';

                $stmt = $this->conn->prepare($query);
                foreach ($params as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
                $stmt->execute();
                return $stmt;
            } else {
                // Count query
                $countQuery = 'SELECT COUNT(*) FROM ' . $this->table . $searchClause;
                $countStmt = $this->conn->prepare($countQuery);
                foreach ($params as $key => $val) {
                    $countStmt->bindValue($key, $val);
                }
                $countStmt->execute();
                $totalRecords = (int)$countStmt->fetchColumn();

                // Paginated query
                $offset = ($page - 1) * $per_page;
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
                    updated_at,
                    deleted_at
                    FROM
                    '. $this->table .'
                    ' . $searchClause . '
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset';

                $stmt = $this->conn->prepare($query);
                foreach ($params as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
                $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();

                return [
                    'stmt' => $stmt,
                    'total' => $totalRecords
                ];
            }
        }

        public function create(){

            $query = 'INSERT INTO ' . $this->table . ' 
                (student_id, first_name, last_name, middle_name, course, course_level, email, password, address, session, profile_pic) 
                VALUES 
                (:student_id, :first_name, :last_name, :middle_name, :course, :course_level, :email, :password, :address, :session, :profile_pic)
                RETURNING id';

            $stmt = $this->conn->prepare($query);

            $this->student_id   = Validator::sanitizeString($this->student_id);
            $this->first_name   = Validator::sanitizeString($this->first_name);
            $this->last_name    = Validator::sanitizeString($this->last_name);
            $this->middle_name  = Validator::sanitizeString($this->middle_name);
            $this->course       = Validator::sanitizeString($this->course);
            $this->course_level = Validator::sanitizeString($this->course_level);
            $this->email        = Validator::sanitizeEmail($this->email);
            $this->address      = Validator::sanitizeString($this->address ?? ''); 
            $this->session      = Validator::isNumeric($this->session) ? (int)$this->session : 30;
            $this->profile_pic  = $this->profile_pic ?? null;

            if (!Validator::validateEmail($this->email)) {
                return false;
            }

            $stmt->bindParam(':student_id',   $this->student_id);
            $stmt->bindParam(':first_name',   $this->first_name);
            $stmt->bindParam(':last_name',    $this->last_name);
            $stmt->bindParam(':middle_name',  $this->middle_name);
            $stmt->bindParam(':course',       $this->course);
            $stmt->bindParam(':course_level', $this->course_level);
            $stmt->bindParam(':email',        $this->email);
            $stmt->bindParam(':password',     $this->password);
            $stmt->bindParam(':address',      $this->address); 
            $stmt->bindParam(':session',      $this->session);
            $stmt->bindParam(':profile_pic',  $this->profile_pic);

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

            $this->student_id = Validator::sanitizeString($this->student_id);
            $stmt->bindParam(':student_id', $this->student_id);

            try {
                $stmt->execute();
                return $stmt->fetch();
            } catch(PDOException $e) {
                echo json_encode(array('message' => $e->getMessage()));
                return false;
            }
        }
        public function read_by_student_id($student_id) {
            $query = 'SELECT
                        id, student_id, first_name, last_name, middle_name,
                        course_level, email, session, course,
                        address, profile_pic, is_active, created_at, updated_at
                    FROM ' . $this->table . '
                    WHERE student_id = :student_id
                    LIMIT 1';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);

            try {
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return null;
            }
        }

        public function read_by_id($id) {
            $query = 'SELECT
                        id, student_id, first_name, last_name, middle_name,
                        course_level, email, session, course,
                        address, profile_pic, is_active, created_at, updated_at
                    FROM ' . $this->table . '
                    WHERE id = :id
                    LIMIT 1';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);

            try {
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return null;
            }
        }

        public function getDetailsByStudentId($student_id) {
            // Get basic profile using the student_id lookup
            $profile = $this->read_by_student_id($student_id);
            if (!$profile) return null;

            // Fetch recent reservations
            $queryRes = "SELECT r.*, l.name as lab_name 
                         FROM reservations r 
                         LEFT JOIN laboratories l ON r.lab_id = l.id 
                         WHERE r.student_id = :student_id 
                         ORDER BY r.reserved_date DESC, r.reserved_time DESC";
            $stmtRes = $this->conn->prepare($queryRes);
            $stmtRes->execute([':student_id' => $student_id]);
            $profile['reservations'] = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

            // Fetch recent sit-in logs
            $queryLogs = "SELECT sl.*, l.name as lab_name 
                          FROM sit_in_logs sl 
                          LEFT JOIN laboratories l ON sl.lab_id = l.id 
                          WHERE sl.student_id = :student_id 
                          ORDER BY sl.time_in DESC";
            $stmtLogs = $this->conn->prepare($queryLogs);
            $stmtLogs->execute([':student_id' => $student_id]);
            $profile['sit_in_logs'] = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $queryStats = "SELECT 
                            COUNT(*) as total_sessions,
                            COALESCE(SUM(EXTRACT(EPOCH FROM (time_out - time_in))/3600), 0) as total_hours
                           FROM sit_in_logs 
                           WHERE student_id = :student_id AND status = 'completed' AND time_out IS NOT NULL";
            $stmtStats = $this->conn->prepare($queryStats);
            $stmtStats->execute([':student_id' => $student_id]);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

            $profile['total_sessions'] = (int)($stats['total_sessions'] ?? 0);
            $profile['total_hours'] = round((float)($stats['total_hours'] ?? 0), 2);


            return $profile;
        }
        // UPDATE student profile
        public function update() {
            $query = 'UPDATE ' . $this->table . ' SET
                        student_id   = :student_id,
                        first_name   = :first_name,
                        last_name    = :last_name,
                        middle_name  = :middle_name,
                        course       = :course,
                        course_level = :course_level,
                        email        = :email,
                        address      = :address,
                        session      = :session,
                        profile_pic  = :profile_pic,
                        updated_at   = NOW()
                    WHERE id = :id';

            $stmt = $this->conn->prepare($query);

            $this->student_id   = Validator::sanitizeString($this->student_id);
            $this->first_name   = Validator::sanitizeString($this->first_name);
            $this->last_name    = Validator::sanitizeString($this->last_name);
            $this->middle_name  = Validator::sanitizeString($this->middle_name);
            $this->course       = Validator::sanitizeString($this->course);
            $this->course_level = Validator::sanitizeString($this->course_level);
            $this->email        = Validator::sanitizeEmail($this->email);
            $this->address      = Validator::sanitizeString($this->address);
            $this->session      = (int)$this->session;
            $this->profile_pic  = $this->profile_pic ?? null;
            $this->id           = Validator::sanitizeString($this->id);

            $stmt->bindParam(':student_id',   $this->student_id);
            $stmt->bindParam(':first_name',   $this->first_name);
            $stmt->bindParam(':last_name',    $this->last_name);
            $stmt->bindParam(':middle_name',  $this->middle_name);
            $stmt->bindParam(':course',       $this->course);
            $stmt->bindParam(':course_level', $this->course_level);
            $stmt->bindParam(':email',        $this->email);
            $stmt->bindParam(':address',      $this->address);
            $stmt->bindParam(':session',      $this->session);
            $stmt->bindParam(':profile_pic',  $this->profile_pic);
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

            $keyword = '%' . Validator::sanitizeString($keyword) . '%';
            $stmt->bindParam(':keyword', $keyword);

            try {
                $stmt->execute();
                return $stmt;
            } catch (PDOException $e) {
                echo json_encode(['message' => $e->getMessage()]);
                return false;
            }
        }

        public function emailExist($exclude_id = null){
            $query = 'SELECT id FROM ' .$this->table. " WHERE email = :email";
            if ($exclude_id) {
                $query .= " AND id != :exclude_id";
            }
            $query .= " LIMIT 1";
            
            $stmt = $this->conn->prepare($query);

            $this->email = Validator::sanitizeEmail($this->email);
            $stmt->bindParam(':email', $this->email);
            if ($exclude_id) {
                $stmt->bindParam(':exclude_id', $exclude_id);
            }

            $stmt->execute();

            return $stmt->rowCount() > 0;
        }

        public function studentIdExist($student_id, $exclude_id = null) {
            $query = 'SELECT id FROM ' . $this->table . " WHERE student_id = :student_id";
            if ($exclude_id) {
                $query .= " AND id != :exclude_id";
            }
            $query .= " LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            if ($exclude_id) {
                $stmt->bindParam(':exclude_id', $exclude_id);
            }

            $stmt->execute();

            return $stmt->rowCount() > 0;
        }

    }

?>