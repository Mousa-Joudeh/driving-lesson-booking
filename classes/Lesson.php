<?php

// Lesson: Responsible for managing driving lesson sessions.

class Lesson
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    // Get all lessons for admin
    public function getAll()
    {
        $sql = "SELECT l.*, i.full_name AS instructor_name
                FROM lesson_session l
                INNER JOIN instructor i
                    ON l.instructor_id = i.instructor_id
                ORDER BY l.lesson_date DESC, l.start_time DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get available lessons for learners
    public function getAvailableLessons($transmissionType = "")
    {
        $sql = "SELECT l.*, i.full_name AS instructor_name
                FROM lesson_session l
                INNER JOIN instructor i
                    ON l.instructor_id = i.instructor_id
                WHERE l.status = 'Scheduled'
                  AND i.status = 'Active'
                  AND TIMESTAMP(l.lesson_date, l.start_time) > NOW()
                  AND NOT EXISTS (
                      SELECT 1
                      FROM booking b
                      WHERE b.lesson_id = l.lesson_id
                        AND b.status = 'Confirmed'
                  )";

        $params = [];

        if (in_array($transmissionType, ["Manual", "Automatic"], true)) {
            $sql .= " AND l.transmission_type = ?";
            $params[] = $transmissionType;
        }

        $sql .= " ORDER BY l.lesson_date ASC, l.start_time ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get lesson by ID
    public function getById($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT) || $id < 1) {
            return false;
        }

        $sql = "SELECT l.*, i.full_name AS instructor_name
                FROM lesson_session l
                INNER JOIN instructor i
                    ON l.instructor_id = i.instructor_id
                WHERE l.lesson_id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Validate lesson information
    private function validateLesson($instructorId, $lessonDate, $transmissionType, $startTime, $endTime, $price)
    {
        if (
            !filter_var($instructorId, FILTER_VALIDATE_INT) ||
            $instructorId < 1 ||
            !in_array($transmissionType, ["Manual", "Automatic"], true) ||
            !is_string($lessonDate) ||
            !is_string($startTime) ||
            !is_string($endTime) ||
            !is_string($price)
        ) {
            return false;
        }

        // Validate date and time formats
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lessonDate) ||
            !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) ||
            !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)
        ) {
            return false;
        }

        $startTime = strlen($startTime) === 5
            ? $startTime . ":00"
            : $startTime;

        $endTime = strlen($endTime) === 5
            ? $endTime . ":00"
            : $endTime;

        $start = DateTime::createFromFormat(
            "!Y-m-d H:i:s",
            $lessonDate . " " . $startTime
        );

        $end = DateTime::createFromFormat(
            "!Y-m-d H:i:s",
            $lessonDate . " " . $endTime
        );

        if (!$start || !$end) {
            return false;
        }

        // Reject invalid dates and times
        if (
            $start->format("Y-m-d H:i:s") !== $lessonDate . " " . $startTime ||
            $end->format("Y-m-d H:i:s") !== $lessonDate . " " . $endTime
        ) {
            return false;
        }

        if ($start <= new DateTime()) {
            return false;
        }

        // Each lesson must last exactly 60 minutes
        if (($end->getTimestamp() - $start->getTimestamp()) !== 3600) {
            return false;
        }

        // Price must fit DECIMAL(8,2)
        if (
            !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $price) ||
            (float) $price <= 0
        ) {
            return false;
        }

        return true;
    }

    // Check instructor availability and transmission type
    private function instructorIsAvailable($instructorId, $lessonDate, $transmissionType, $startTime, $endTime, $excludeLessonId = 0)
    {
        $sql = "SELECT transmission_type, status
                FROM instructor
                WHERE instructor_id = ?
                FOR UPDATE";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$instructorId]);

        $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instructor || $instructor["status"] !== "Active") {
            return false;
        }

        if (
            $instructor["transmission_type"] !== "Both" &&
            $instructor["transmission_type"] !== $transmissionType
        ) {
            return false;
        }

        $sql = "SELECT lesson_id
                FROM lesson_session
                WHERE instructor_id = ?
                  AND lesson_date = ?
                  AND status = 'Scheduled'
                  AND start_time < ?
                  AND end_time > ?
                  AND lesson_id != ?
                LIMIT 1
                FOR UPDATE";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $instructorId,
            $lessonDate,
            $endTime,
            $startTime,
            $excludeLessonId
        ]);

        return $stmt->fetch() === false;
    }

    // Add a new lesson
    public function add($instructorId, $lessonDate, $transmissionType, $startTime, $endTime, $price)
    {
        if (!$this->validateLesson($instructorId, $lessonDate, $transmissionType, $startTime, $endTime, $price)) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            if (!$this->instructorIsAvailable($instructorId, $lessonDate, $transmissionType, $startTime, $endTime)) {
                $this->conn->rollBack();
                return false;
            }

            $sql = "INSERT INTO lesson_session
                    (instructor_id, lesson_date, transmission_type, start_time, end_time, price, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')";

            $stmt = $this->conn->prepare($sql);

            $result = $stmt->execute([
                $instructorId,
                $lessonDate,
                $transmissionType,
                $startTime,
                $endTime,
                $price
            ]);

            $this->conn->commit();

            return $result;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Add lesson failed: " . $e->getMessage());
            return false;
        }
    }

    // Update an existing lesson
    public function update($id, $instructorId, $lessonDate, $transmissionType, $startTime, $endTime, $price)
    {
        if (
            !filter_var($id, FILTER_VALIDATE_INT) ||
            $id < 1 ||
            !$this->validateLesson($instructorId, $lessonDate, $transmissionType, $startTime, $endTime, $price)
        ) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Find the current instructor
            $sql = "SELECT instructor_id
                    FROM lesson_session
                    WHERE lesson_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            $currentLesson = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentLesson) {
                $this->conn->rollBack();
                return false;
            }

            // Lock instructor records in a consistent order
            $instructorIds = array_unique([
                (int) $currentLesson["instructor_id"],
                (int) $instructorId
            ]);

            sort($instructorIds, SORT_NUMERIC);

            foreach ($instructorIds as $lockedInstructorId) {
                $sql = "SELECT instructor_id
                        FROM instructor
                        WHERE instructor_id = ?
                        FOR UPDATE";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$lockedInstructorId]);

                if ($stmt->fetch() === false) {
                    $this->conn->rollBack();
                    return false;
                }
            }

            // Lock the lesson before checking bookings
            $sql = "SELECT instructor_id, status
                    FROM lesson_session
                    WHERE lesson_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            $lockedLesson = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$lockedLesson ||
                $lockedLesson["status"] !== "Scheduled" ||
                (int) $lockedLesson["instructor_id"] !== (int) $currentLesson["instructor_id"]
            ) {
                $this->conn->rollBack();
                return false;
            }

            // Prevent changes to lessons that already have confirmed bookings
            $sql = "SELECT booking_id
                    FROM booking
                    WHERE lesson_id = ?
                      AND status = 'Confirmed'
                    LIMIT 1
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            if ($stmt->fetch() !== false) {
                $this->conn->rollBack();
                return false;
            }

            if (!$this->instructorIsAvailable($instructorId, $lessonDate, $transmissionType, $startTime, $endTime, $id)) {
                $this->conn->rollBack();
                return false;
            }

            $sql = "UPDATE lesson_session
                    SET instructor_id = ?,
                        lesson_date = ?,
                        transmission_type = ?,
                        start_time = ?,
                        end_time = ?,
                        price = ?
                    WHERE lesson_id = ?
                      AND status = 'Scheduled'";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                $instructorId,
                $lessonDate,
                $transmissionType,
                $startTime,
                $endTime,
                $price,
                $id
            ]);

            $result = $stmt->rowCount() > 0;

            $this->conn->commit();

            return $result;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Update lesson failed: " . $e->getMessage());
            return false;
        }
    }

    // Cancel a lesson
    public function cancel($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT) || $id < 1) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Lock the lesson before checking bookings
            $sql = "SELECT lesson_id, status
                    FROM lesson_session
                    WHERE lesson_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lesson || $lesson["status"] !== "Scheduled") {
                $this->conn->rollBack();
                return false;
            }

            // Prevent cancelling lessons with confirmed bookings
            $sql = "SELECT booking_id
                    FROM booking
                    WHERE lesson_id = ?
                      AND status = 'Confirmed'
                    LIMIT 1
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            if ($stmt->fetch() !== false) {
                $this->conn->rollBack();
                return false;
            }

            $sql = "UPDATE lesson_session
                    SET status = 'Cancelled'
                    WHERE lesson_id = ?
                      AND status = 'Scheduled'";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            $result = $stmt->rowCount() > 0;

            $this->conn->commit();

            return $result;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Cancel lesson failed: " . $e->getMessage());
            return false;
        }
    }
}