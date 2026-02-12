<?php
require_once __DIR__ . '/../initialize.php';

class DBConnection {

    public $conn;

    public function __construct() {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conn = new mysqli(
                DB_SERVER,
                DB_USERNAME,
                DB_PASSWORD,
                DB_NAME,
                3306  // Port number here
            );
            $this->conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}