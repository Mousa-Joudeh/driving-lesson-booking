<?php

// Instructor: Responsible for managing driving instructors.

class Instructor
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    // Get all instructors
    public function getAll()
    {
        $sql = "SELECT instructor_id, full_name, email, phone,
                       transmission_type, status
                FROM instructor
                ORDER BY instructor_id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get active instructors
    public function getActive()
    {
        $sql = "SELECT instructor_id, full_name, email, phone,
                       transmission_type, status
                FROM instructor
                WHERE status = 'Active'
                ORDER BY full_name ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get instructor by ID
    public function getById($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT) || $id < 1) {
            return false;
        }

        $sql = "SELECT instructor_id, full_name, email, phone,
                       transmission_type, status
                FROM instructor
                WHERE instructor_id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Add a new instructor
    public function add($fullName, $email, $phone, $transmissionType)
    {
        if (
            !is_string($fullName) ||
            !is_string($email) ||
            !is_string($phone) ||
            !is_string($transmissionType)
        ) {
            return false;
        }

        $fullName = trim($fullName);
        $email = trim($email);
        $phone = trim($phone);

        if (
            $fullName === "" ||
            mb_strlen($fullName) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150 ||
            !preg_match('/^07[789][0-9]{7}$/', $phone) ||
            !in_array($transmissionType, ["Manual", "Automatic", "Both"], true)
        ) {
            return false;
        }

        try {
            $sql = "INSERT INTO instructor
                    (full_name, email, phone, transmission_type, status)
                    VALUES (?, ?, ?, ?, 'Active')";

            $stmt = $this->conn->prepare($sql);

            return $stmt->execute([
                $fullName,
                $email,
                $phone,
                $transmissionType
            ]);

        } catch (PDOException $e) {
            error_log("Add instructor failed: " . $e->getMessage());
            return false;
        }
    }

    // Update instructor information
    public function update($id, $fullName, $email, $phone, $transmissionType)
    {
        if (
            !filter_var($id, FILTER_VALIDATE_INT) ||
            $id < 1 ||
            !is_string($fullName) ||
            !is_string($email) ||
            !is_string($phone) ||
            !is_string($transmissionType)
        ) {
            return false;
        }

        $fullName = trim($fullName);
        $email = trim($email);
        $phone = trim($phone);

        if (
            $fullName === "" ||
            mb_strlen($fullName) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150 ||
            !preg_match('/^07[789][0-9]{7}$/', $phone) ||
            !in_array($transmissionType, ["Manual", "Automatic", "Both"], true)
        ) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Lock the instructor before updating information
            $sql = "SELECT instructor_id, transmission_type
                    FROM instructor
                    WHERE instructor_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$instructor) {
                $this->conn->rollBack();
                return false;
            }

            // Check for conflicting future lessons when changing transmission type
            if (
                $transmissionType !== "Both" &&
                $transmissionType !== $instructor["transmission_type"]
            ) {
                $sql = "SELECT lesson_id
                        FROM lesson_session
                        WHERE instructor_id = ?
                          AND status = 'Scheduled'
                          AND transmission_type != ?
                          AND TIMESTAMP(lesson_date, end_time) > NOW()
                        LIMIT 1";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$id, $transmissionType]);

                if ($stmt->fetch() !== false) {
                    $this->conn->rollBack();
                    return false;
                }
            }

            $sql = "UPDATE instructor
                    SET full_name = ?,
                        email = ?,
                        phone = ?,
                        transmission_type = ?
                    WHERE instructor_id = ?";

            $stmt = $this->conn->prepare($sql);

            $result = $stmt->execute([
                $fullName,
                $email,
                $phone,
                $transmissionType,
                $id
            ]);

            $this->conn->commit();

            return $result;

        } catch (PDOException $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Update instructor failed: " . $e->getMessage());
            return false;
        }
    }

    // Change instructor status
    public function updateStatus($id, $status)
    {
        if (
            !filter_var($id, FILTER_VALIDATE_INT) ||
            $id < 1 ||
            !is_string($status) ||
            !in_array($status, ["Active", "Inactive"], true)
        ) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Lock the instructor while checking and updating the status
            $sql = "SELECT instructor_id
                    FROM instructor
                    WHERE instructor_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            if ($stmt->fetch() === false) {
                $this->conn->rollBack();
                return false;
            }

            if ($status === "Inactive") {

                // Check for future scheduled lessons
                $sql = "SELECT lesson_id
                        FROM lesson_session
                        WHERE instructor_id = ?
                          AND status = 'Scheduled'
                          AND TIMESTAMP(lesson_date, end_time) > NOW()
                        LIMIT 1";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$id]);

                if ($stmt->fetch() !== false) {
                    $this->conn->rollBack();
                    return false;
                }
            }

            $sql = "UPDATE instructor
                    SET status = ?
                    WHERE instructor_id = ?";

            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$status, $id]);

            $this->conn->commit();

            return $result;

        } catch (PDOException $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Update instructor status failed: " . $e->getMessage());
            return false;
        }
    }
}