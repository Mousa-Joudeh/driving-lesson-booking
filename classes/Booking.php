<?php

// Booking: Responsible for managing driving lesson bookings.

class Booking
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    // Get all bookings for admin
    public function getAll()
    {
        $sql = "SELECT b.booking_id, b.user_id, b.lesson_id,
                       b.booking_date, b.status, b.cancelled_at,
                       u.full_name AS learner_name,
                       u.email AS learner_email,
                       l.lesson_date, l.start_time, l.end_time,
                       l.transmission_type, l.price,
                       i.full_name AS instructor_name
                FROM booking b
                INNER JOIN `user` u ON b.user_id = u.user_id
                INNER JOIN lesson_session l ON b.lesson_id = l.lesson_id
                INNER JOIN instructor i ON l.instructor_id = i.instructor_id
                ORDER BY b.booking_date DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get bookings for a specific learner
    public function getByUserId($userId)
    {
        if (!filter_var($userId, FILTER_VALIDATE_INT) || $userId < 1) {
            return [];
        }

        $sql = "SELECT b.booking_id, b.lesson_id, b.booking_date,
                       b.status, b.cancelled_at,
                       l.lesson_date, l.start_time, l.end_time,
                       l.transmission_type, l.price,
                       i.full_name AS instructor_name
                FROM booking b
                INNER JOIN lesson_session l ON b.lesson_id = l.lesson_id
                INNER JOIN instructor i ON l.instructor_id = i.instructor_id
                WHERE b.user_id = ?
                ORDER BY l.lesson_date DESC, l.start_time DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Book an available lesson
    public function book($userId, $lessonId)
    {
        if (
            !filter_var($userId, FILTER_VALIDATE_INT) ||
            $userId < 1 ||
            !filter_var($lessonId, FILTER_VALIDATE_INT) ||
            $lessonId < 1
        ) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Lock the learner to prevent overlapping bookings
            $sql = "SELECT user_id, role
                    FROM `user`
                    WHERE user_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId]);

            $learner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$learner || $learner["role"] !== "Learner") {
                $this->conn->rollBack();
                return false;
            }

            // Lock the lesson to prevent double booking
            $sql = "SELECT lesson_id, instructor_id, status,
                           lesson_date, start_time, end_time
                    FROM lesson_session
                    WHERE lesson_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lessonId]);

            $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lesson || $lesson["status"] !== "Scheduled") {
                $this->conn->rollBack();
                return false;
            }

            // Check that the instructor is active
            $sql = "SELECT status
                    FROM instructor
                    WHERE instructor_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lesson["instructor_id"]]);

            $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$instructor || $instructor["status"] !== "Active") {
                $this->conn->rollBack();
                return false;
            }

            // Check that the lesson has not started
            $sql = "SELECT TIMESTAMP(?, ?) > NOW() AS is_future";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                $lesson["lesson_date"],
                $lesson["start_time"]
            ]);

            $timeCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$timeCheck || !$timeCheck["is_future"]) {
                $this->conn->rollBack();
                return false;
            }

            // Check whether the lesson already has a confirmed booking
            $sql = "SELECT booking_id
                    FROM booking
                    WHERE lesson_id = ?
                      AND status = 'Confirmed'
                    LIMIT 1
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lessonId]);

            if ($stmt->fetch() !== false) {
                $this->conn->rollBack();
                return false;
            }

            // Check whether the learner has an overlapping booking
            $sql = "SELECT b.booking_id
                    FROM booking b
                    INNER JOIN lesson_session l
                        ON b.lesson_id = l.lesson_id
                    WHERE b.user_id = ?
                      AND b.status = 'Confirmed'
                      AND l.lesson_date = ?
                      AND l.start_time < ?
                      AND l.end_time > ?
                    LIMIT 1
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                $userId,
                $lesson["lesson_date"],
                $lesson["end_time"],
                $lesson["start_time"]
            ]);

            if ($stmt->fetch() !== false) {
                $this->conn->rollBack();
                return false;
            }

            // Create a confirmed booking
            $sql = "INSERT INTO booking
                    (user_id, lesson_id, status)
                    VALUES (?, ?, 'Confirmed')";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId, $lessonId]);

            $this->conn->commit();

            return true;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Booking failed: " . $e->getMessage());
            return false;
        }
    }

    // Cancel a booking at least 24 hours before the lesson
    public function cancel($bookingId, $userId)
    {
        if (
            !filter_var($bookingId, FILTER_VALIDATE_INT) ||
            $bookingId < 1 ||
            !filter_var($userId, FILTER_VALIDATE_INT) ||
            $userId < 1
        ) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Lock the learner first
            $sql = "SELECT user_id
                    FROM `user`
                    WHERE user_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId]);

            if ($stmt->fetch() === false) {
                $this->conn->rollBack();
                return false;
            }

            // Find the lesson associated with the booking
            $sql = "SELECT lesson_id
                    FROM booking
                    WHERE booking_id = ?
                      AND user_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingId, $userId]);

            $bookingInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bookingInfo) {
                $this->conn->rollBack();
                return false;
            }

            // Lock the lesson before the booking
            $sql = "SELECT lesson_id
                    FROM lesson_session
                    WHERE lesson_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingInfo["lesson_id"]]);

            if ($stmt->fetch() === false) {
                $this->conn->rollBack();
                return false;
            }

            // Lock the booking before cancelling
            $sql = "SELECT b.booking_id, b.status,
                           l.lesson_date, l.start_time
                    FROM booking b
                    INNER JOIN lesson_session l
                        ON b.lesson_id = l.lesson_id
                    WHERE b.booking_id = ?
                      AND b.user_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingId, $userId]);

            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking || $booking["status"] !== "Confirmed") {
                $this->conn->rollBack();
                return false;
            }

            // Check that at least 24 hours remain
            $sql = "SELECT TIMESTAMP(?, ?) >=
                           DATE_ADD(NOW(), INTERVAL 24 HOUR)
                           AS can_cancel";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                $booking["lesson_date"],
                $booking["start_time"]
            ]);

            $timeCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$timeCheck || !$timeCheck["can_cancel"]) {
                $this->conn->rollBack();
                return false;
            }

            // Update booking status
            $sql = "UPDATE booking
                    SET status = 'Cancelled',
                        cancelled_at = NOW()
                    WHERE booking_id = ?
                      AND user_id = ?
                      AND status = 'Confirmed'";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingId, $userId]);

            $result = $stmt->rowCount() > 0;

            $this->conn->commit();

            return $result;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Cancel booking failed: " . $e->getMessage());
            return false;
        }
    }

    // Mark a booking as completed
    public function complete($bookingId)
    {
        if (!filter_var($bookingId, FILTER_VALIDATE_INT) || $bookingId < 1) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Find the learner and lesson for this booking
            $sql = "SELECT user_id, lesson_id
                    FROM booking
                    WHERE booking_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingId]);

            $bookingInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bookingInfo) {
                $this->conn->rollBack();
                return false;
            }

            // Lock the learner first
            $sql = "SELECT user_id
                    FROM `user`
                    WHERE user_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingInfo["user_id"]]);

            if ($stmt->fetch() === false) {
                $this->conn->rollBack();
                return false;
            }

            // Lock the lesson
            $sql = "SELECT lesson_id, lesson_date, end_time
                    FROM lesson_session
                    WHERE lesson_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingInfo["lesson_id"]]);

            $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lesson) {
                $this->conn->rollBack();
                return false;
            }

            // Lock the booking before completing
            $sql = "SELECT booking_id, status, user_id, lesson_id
                    FROM booking
                    WHERE booking_id = ?
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingId]);

            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$booking ||
                $booking["status"] !== "Confirmed" ||
                (int) $booking["user_id"] !== (int) $bookingInfo["user_id"] ||
                (int) $booking["lesson_id"] !== (int) $bookingInfo["lesson_id"]
            ) {
                $this->conn->rollBack();
                return false;
            }

            // Check that the lesson has ended
            $sql = "SELECT TIMESTAMP(?, ?) <= NOW() AS has_ended";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                $lesson["lesson_date"],
                $lesson["end_time"]
            ]);

            $timeCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$timeCheck || !$timeCheck["has_ended"]) {
                $this->conn->rollBack();
                return false;
            }

            // Mark a booking as completed
            $sql = "UPDATE booking
                    SET status = 'Completed'
                    WHERE booking_id = ?
                      AND status = 'Confirmed'";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$bookingId]);

            $result = $stmt->rowCount() > 0;

            $this->conn->commit();

            return $result;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Complete booking failed: " . $e->getMessage());
            return false;
        }
    }
}