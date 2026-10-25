<?php

require_once __DIR__ . "/config/auth.php";
require_once __DIR__ . "/config/Database.php";
require_once __DIR__ . "/classes/Booking.php";

requireLogin();

// Only learners can access their bookings
if (($_SESSION["role"] ?? "") !== "Learner") {
    http_response_code(403);
    exit("Access denied.");
}

$database = new Database();
$conn = $database->connect();

$booking = new Booking($conn);

$message = "";
$messageType = "";

// Handle cancellation request
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!validateCsrfToken()) {
        http_response_code(403);
        exit("Invalid CSRF token.");
    }

    $bookingId = $_POST["booking_id"] ?? "";

    if (
        !is_string($bookingId) ||
        !filter_var($bookingId, FILTER_VALIDATE_INT) ||
        (int) $bookingId < 1
    ) {
        http_response_code(400);
        $message = "Invalid booking selected.";
        $messageType = "error";

    } else {

        $userId = $_SESSION["user_id"];

        $result = $booking->cancel($bookingId, $userId);

        if ($result === true) {
            $_SESSION["flash_message"] = "Booking cancelled successfully!";
            $_SESSION["flash_type"] = "success";
        } else {
            $_SESSION["flash_message"] = "Cancellation failed. You can only cancel confirmed bookings at least 24 hours before the lesson.";
            $_SESSION["flash_type"] = "error";
        }

        // Redirect to prevent duplicate form submission
        header("Location: my-bookings.php");
        exit;
    }
}

// Display message after redirect
if (isset($_SESSION["flash_message"])) {

    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"] ?? "error";

    unset($_SESSION["flash_message"]);
    unset($_SESSION["flash_type"]);
}

// Get bookings for the logged-in learner
$userId = $_SESSION["user_id"];
$myBookings = $booking->getByUserId($userId);

$userName = $_SESSION["user_name"] ?? "Learner";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Bookings | Driving Lesson Booking</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="dashboard-page my-bookings-page">

    <header class="site-header">

        <nav class="navbar container" aria-label="Main navigation">

            <a href="lessons.php" class="navbar-brand">
                Driving Lesson Booking
            </a>

            <div class="navbar-links">

                <a href="lessons.php" class="nav-link">
                    Available Lessons
                </a>

                <a href="my-bookings.php" class="nav-link active" aria-current="page">
                    My Bookings
                </a>

                <form action="logout.php" method="POST" class="logout-form">

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                    <button type="submit" class="btn btn-outline">
                        Logout
                    </button>

                </form>

            </div>

        </nav>

    </header>

    <main class="container main-content">

        <section class="page-header">

            <h1>My Bookings</h1>

            <p>
                Welcome,
                <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>!
                Manage and review your driving lesson bookings.
            </p>

        </section>

        <?php if (!empty($message)): ?>

        <div class="message message-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <?php endif; ?>

        <section class="bookings-section" aria-labelledby="bookings-heading">

            <div class="section-header">

                <h2 id="bookings-heading">Your Bookings</h2>

                <p class="results-count">
                    <?= count($myBookings) ?> booking(s)
                </p>

            </div>

            <?php if (empty($myBookings)): ?>

            <div class="empty-state">

                <h3>No Bookings Yet</h3>

                <p>
                    You haven't booked any driving lessons yet.
                    Browse available lessons to get started.
                </p>

                <a href="lessons.php" class="btn btn-primary">
                    Browse Lessons
                </a>

            </div>

            <?php else: ?>

            <div class="bookings-grid">

                <?php foreach ($myBookings as $myBooking): ?>

                <?php

                        $lessonStart = strtotime(
                            $myBooking["lesson_date"] . " " .
                            $myBooking["start_time"]
                        );

                        $canCancel = (
                            $myBooking["status"] === "Confirmed" &&
                            $lessonStart !== false &&
                            ($lessonStart - time()) >= 86400
                        );

                        ?>

                <article class="booking-card">

                    <div class="booking-card-header">

                        <h3>
                            Booking #<?= (int) $myBooking["booking_id"] ?>
                        </h3>

                        <span
                            class="status-badge status-<?= strtolower(htmlspecialchars($myBooking["status"], ENT_QUOTES, 'UTF-8')) ?>">
                            <?= htmlspecialchars($myBooking["status"], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                    <div class="booking-card-body">

                        <p class="booking-detail">
                            <strong>Instructor:</strong>
                            <span>
                                <?= htmlspecialchars($myBooking["instructor_name"], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                        <p class="booking-detail">
                            <strong>Transmission:</strong>
                            <span>
                                <?= htmlspecialchars($myBooking["transmission_type"], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                        <p class="booking-detail">
                            <strong>Date:</strong>
                            <span>
                                <?= htmlspecialchars($myBooking["lesson_date"], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                        <p class="booking-detail">
                            <strong>Time:</strong>
                            <span>
                                <?= htmlspecialchars(substr($myBooking["start_time"], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                                -
                                <?= htmlspecialchars(substr($myBooking["end_time"], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                        <p class="booking-detail">
                            <strong>Price:</strong>
                            <span>
                                <?= number_format((float) $myBooking["price"], 2) ?>
                                JOD
                            </span>
                        </p>

                        <p class="booking-detail">
                            <strong>Booked On:</strong>
                            <span>
                                <?= htmlspecialchars($myBooking["booking_date"], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                    </div>

                    <div class="booking-card-footer">

                        <?php if ($canCancel): ?>

                        <form action="my-bookings.php" method="POST" class="cancel-booking-form">

                            <input type="hidden" name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                            <input type="hidden" name="booking_id" value="<?= (int) $myBooking["booking_id"] ?>">

                            <button type="submit" class="btn btn-danger">
                                Cancel Booking
                            </button>

                        </form>

                        <?php elseif ($myBooking["status"] === "Confirmed"): ?>

                        <p class="booking-note">
                            Cancellation is unavailable within 24 hours of the lesson.
                        </p>

                        <?php elseif ($myBooking["status"] === "Cancelled"): ?>

                        <p class="booking-note">
                            This booking has been cancelled.
                        </p>

                        <?php elseif ($myBooking["status"] === "Completed"): ?>

                        <p class="booking-note">
                            This lesson has been completed.
                        </p>

                        <?php endif; ?>

                    </div>

                </article>

                <?php endforeach; ?>

            </div>

            <?php endif; ?>

        </section>

    </main>

    <script src="assets/js/main.js" defer></script>

</body>

</html>