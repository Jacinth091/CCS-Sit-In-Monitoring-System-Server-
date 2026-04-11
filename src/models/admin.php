<?php

class Admin {
    private $conn;
    private $table = 'admins';

    public $id;
    public $username;
    public $first_name;
    public $last_name;
    public $email;
    public $password;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login() {
        $query = 'SELECT id, username, first_name, last_name, email, password 
                  FROM ' . $this->table . ' 
                  WHERE username = :username 
                  LIMIT 1';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $this->username);

        try {
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
}