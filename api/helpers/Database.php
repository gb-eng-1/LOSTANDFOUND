<?php

class Database {
    // Default XAMPP credentials
    private $host = 'localhost';
    private $db_name = 'lostandfound';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // Fail gracefully if DB is not reachable
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed.");
        }
        return $this->conn;
    }
}