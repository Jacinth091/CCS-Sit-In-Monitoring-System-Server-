<?php

class Testimonial {
    private $conn;
    private $table = 'testimonials';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($student_id, $content, $rating = 5, $is_anonymous = true) {
        $query = "INSERT INTO " . $this->table . " (student_id, content, rating, is_anonymous) VALUES (:student_id, :content, :rating, :is_anonymous)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':is_anonymous', $is_anonymous, PDO::PARAM_BOOL);
        return $stmt->execute();
    }

    public function read($include_unapproved = false, $page = null, $per_page = null, $status = null, $search = null, $unique_students = false) {
        $params = [];
        $clauses = ["t.deleted_at IS NULL"];

        if (!$include_unapproved) {
            $clauses[] = "t.is_approved = TRUE";
        } else if ($status !== null && $status !== '') {
            if ($status === 'approved') {
                $clauses[] = "t.is_approved = TRUE";
            } else if ($status === 'pending') {
                $clauses[] = "t.is_approved = FALSE";
            } else if ($status === 'anonymous') {
                $clauses[] = "t.is_anonymous = TRUE";
            }
        }

        if ($search !== null && trim($search) !== '') {
            $clauses[] = "(t.content ILIKE :search OR s.first_name ILIKE :search OR s.last_name ILIKE :search OR s.student_id ILIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $whereClause = "";
        if (count($clauses) > 0) {
            $whereClause = " WHERE " . implode(" AND ", $clauses);
        }

        if ($page === null) {
            if ($unique_students) {
                $query = "SELECT * FROM (
                            SELECT DISTINCT ON (t.student_id) t.*, s.first_name, s.last_name, s.profile_pic, s.course, s.course_level 
                            FROM " . $this->table . " t
                            JOIN students s ON t.student_id = s.student_id" . $whereClause . "
                            ORDER BY t.student_id, RANDOM()
                          ) unique_t
                          ORDER BY RANDOM()";
            } else {
                $query = "SELECT t.*, s.first_name, s.last_name, s.profile_pic, s.course, s.course_level 
                          FROM " . $this->table . " t
                          JOIN students s ON t.student_id = s.student_id" . $whereClause . "
                          ORDER BY t.created_at DESC";
            }
            
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mask names for anonymous testimonials if it's a public view
            if (!$include_unapproved) {
                return array_map(function($row) {
                    if (isset($row['is_anonymous']) && $row['is_anonymous']) {
                        $row['first_name'] = 'Anonymous';
                        $row['last_name'] = 'Student';
                        $row['profile_pic'] = null;
                        $row['course'] = 'Verified CCS Student';
                        $row['course_level'] = '';
                        unset($row['student_id']);
                    }
                    return $row;
                }, $results);
            }
            return $results;
        } else {
            // Count query
            $countQuery = "SELECT COUNT(*) 
                          FROM " . $this->table . " t
                          JOIN students s ON t.student_id = s.student_id" . $whereClause;
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($params as $key => $val) {
                $countStmt->bindValue($key, $val);
            }
            $countStmt->execute();
            $totalRecords = (int)$countStmt->fetchColumn();

            // Paginated query
            $offset = ($page - 1) * $per_page;
            $query = "SELECT t.*, s.first_name, s.last_name, s.profile_pic, s.course, s.course_level 
                      FROM " . $this->table . " t
                      JOIN students s ON t.student_id = s.student_id" . $whereClause . "
                      ORDER BY t.created_at DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mask names for anonymous testimonials if it's a public view
            if (!$include_unapproved) {
                $results = array_map(function($row) {
                    if (isset($row['is_anonymous']) && $row['is_anonymous']) {
                        $row['first_name'] = 'Anonymous';
                        $row['last_name'] = 'Student';
                        $row['profile_pic'] = null;
                        $row['course'] = 'Verified CCS Student';
                        $row['course_level'] = '';
                        unset($row['student_id']);
                    }
                    return $row;
                }, $results);
            }

            return [
                'records' => $results,
                'total' => $totalRecords
            ];
        }
    }

    public function getByStudent($student_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE student_id = :student_id AND deleted_at IS NULL ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $is_approved) {
        $query = "UPDATE " . $this->table . " SET is_approved = :is_approved, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':is_approved', $is_approved, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function delete($id) {
        $query = "UPDATE " . $this->table . " SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
