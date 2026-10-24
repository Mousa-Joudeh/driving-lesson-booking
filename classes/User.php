<!-- User: Responsible for the logic of interacting with users. -->
<?php

class User
{
    private $conn;
    
    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function register($fullName, $email, $password, $phone)
    {
        $fullName = trim($fullName);
        if ($fullName === "") {
            return false;
        }
        
        $email = trim($email);

        // filter_var: A ready-made function in PHP to examine or manipulate data
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // strlen: It is a built-in PHP function that returns the length of a string in bytes.
        if (strlen($password) < 8) {
            return false;
        }

        $phone = trim($phone);

        // preg_match: It is a PHP function that uses Regular Expressions (Regex) to check whether a string matches a specific pattern.
        if (!preg_match('/^07[789][0-9]{7}$/', $phone)) {
            return false;
        }

        // password_hash: It is a built-in PHP function that converts a password into a secure password hash, using an algorithm specifically designed for storing passwords.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "SELECT user_id FROM `user` WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser !== false) {
            return false;
        }

        $sql = "INSERT INTO `user` (full_name, email, password, phone) VALUES (?, ?, ?, ?)";

        try {
            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                $fullName,
                $email,
                $hashedPassword,
                $phone
            ]);

            return true;

        } catch (PDOException $e) {
            error_log("Registration failed: " . $e->getMessage());
            return false;
        }
    }

    public function login($email, $password)
    {
        $email = trim($email);

        $sql = "SELECT user_id, full_name, email, password, role
                FROM `user`
                WHERE email = ?";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {
            
            unset($user["password"]);    
        
            return $user;
        }

        return false;
    }
}