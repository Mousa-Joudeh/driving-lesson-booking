<!-- Database: Responsible for establishing the database connection. -->
<?php
class Database
{
    private $host = "127.0.0.1";
    private $db_name = "driving_lesson_booking_db";
    private $username = "root";
    private $password = "";
    private $conn;

    public function connect()
    {
        $this->conn = null;
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
        try {

            $this->conn = new PDO($dsn, $this->username, $this->password);
        }catch (PDOException $e) {
            // PDOException: The type of exception that can result from PDO operations.
            // $e: A variable that represents the Exception that occurred; we can extract error information from it.
            
            error_log("Database connection failed: " . $e->getMessage());
            // We used `error_log()` instead of `echo` so that we wouldn't expose sensitive connection details to the site visitor.
            throw new RuntimeException("Unable to connect to the database.");
        }
        return $this->conn;

    }
}