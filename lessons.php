<?php

require_once __DIR__ . "/config/auth.php";
require_once __DIR__ . "/config/Database.php";
require_once __DIR__ . "/classes/Lesson.php";
require_once __DIR__ . "/classes/Booking.php";

requireLogin();

// Only learners can book lessons
if (($_SESSION["role"] ?? "") !== "Learner") {
    http_response_code(403);
    exit("Access denied.");
}

$database = new Database();
$conn = $database->connect();

$lesson = new Lesson($conn);
$booking = new Booking($conn);

$message = "";
$messageType = "";

// Get transmission type filter
$transmissionType = $_GET["transmission_type"] ?? "";

if (
    !is_string($transmissionType) ||
    !in_array($transmissionType, ["", "Manual", "Automatic"], true)
) {
    $transmissionType = "";
}

// Handle booking request
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!validateCsrfToken()) {
        http_response_code(403);
        exit("Invalid CSRF token.");
    }

    $lessonId = $_POST["lesson_id"] ?? "";

    if (
        !is_scalar($lessonId) ||
        !filter_var($lessonId, FILTER_VALIDATE_INT)
    ) {
        $message = "Invalid lesson selected.";
        $messageType = "error";

    } else {

        $userId = $_SESSION["user_id"];

        $result = $booking->book($userId, $lessonId);

        if ($result === true) {
            $_SESSION["flash_message"] = "Lesson booked successfully!";
            $_SESSION["flash_type"] = "success";

        } else {
            $_SESSION["flash_message"] = "Booking failed. The lesson may no longer be available, or it may conflict with another booking.";
            $_SESSION["flash_type"] = "error";
        }

        // Redirect to prevent duplicate form submission
        header("Location: lessons.php");
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

// Get available lessons
$availableLessons = $lesson->getAvailableLessons($transmissionType);

$userName = $_SESSION["user_name"] ?? "Learner";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Available Lessons | Driving Lesson Booking</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="dashboard-page lessons-page">

    <header class="site-header">

        <nav class="navbar container" aria-label="Main navigation">

            <a href="lessons.php" class="navbar-brand">
                Driving Lesson Booking
            </a>

            <div class="navbar-links">

                <a href="lessons.php" class="nav-link active" aria-current="page">
                    Available Lessons
                </a>

                <a href="my-bookings.php" class="nav-link">
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

            <h1>Available Driving Lessons</h1>

            <p>
                Welcome,
                <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>!
                Choose a suitable lesson and book your session.
            </p>

        </section>

        <?php if (!empty($message)): ?>

        <div class="message message-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <?php endif; ?>

        <section class="filter-section" aria-labelledby="filter-heading">

            <h2 id="filter-heading">Filter Lessons</h2>

            <form action="lessons.php" method="GET" class="filter-form">

                <div class="form-group">

                    <label for="transmission_type">
                        Transmission Type
                    </label>

                    <select name="transmission_type" id="transmission_type" class="form-input">

                        <option value="" <?= $transmissionType === "" ? "selected" : "" ?>>
                            All Types
                        </option>

                        <option value="Manual" <?= $transmissionType === "Manual" ? "selected" : "" ?>>
                            Manual
                        </option>

                        <option value="Automatic" <?= $transmissionType === "Automatic" ? "selected" : "" ?>>
                            Automatic
                        </option>

                    </select>

                </div>

                <button type="submit" class="btn btn-primary">
                    Apply Filter
                </button>

                <a href="lessons.php" class="btn btn-outline">
                    Reset
                </a>

            </form>

        </section>

        <section class="lessons-section" aria-labelledby="lessons-heading">

            <div class="section-header">

                <h2 id="lessons-heading">Lessons</h2>

                <p class="results-count">
                    <?= count($availableLessons) ?>
                    available lesson(s)
                </p>

            </div>

            <?php if (empty($availableLessons)): ?>

            <div class="empty-state">

                <h3>No Lessons Available</h3>

                <p>
                    There are currently no available lessons
                    matching your selection.
                </p>

                <a href="lessons.php" class="btn btn-primary">
                    View All Lessons
                </a>

            </div>

            <?php else: ?>

            <div class="lessons-grid">

                <?php foreach ($availableLessons as $availableLesson): ?>

                <article class="lesson-card">

                    <div class="lesson-card-header">

                        <h3>
                            <?= htmlspecialchars($availableLesson["instructor_name"], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <span class="lesson-badge">
                            <?= htmlspecialchars($availableLesson["transmission_type"], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                    <div class="lesson-card-body">

                        <p class="lesson-detail">
                            <strong>Date:</strong>
                            <span>
                                <?= htmlspecialchars($availableLesson["lesson_date"], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                        <p class="lesson-detail">
                            <strong>Start Time:</strong>
                            <span>
                                <?= htmlspecialchars(substr($availableLesson["start_time"], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                        <p class="lesson-detail">
                            <strong>End Time:</strong>
                            <span>
                                <?= htmlspecialchars(substr($availableLesson["end_time"], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                        <p class="lesson-detail">
                            <strong>Duration:</strong>
                            <span>60 Minutes</span>
                        </p>

                        <p class="lesson-price">
                            <strong>Price:</strong>
                            <span>
                                <?= number_format((float) $availableLesson["price"], 2) ?>
                                JOD
                            </span>
                        </p>

                    </div>

                    <div class="lesson-card-footer">

                        <form action="lessons.php" method="POST" class="booking-form">

                            <input type="hidden" name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                            <input type="hidden" name="lesson_id" value="<?= (int) $availableLesson["lesson_id"] ?>">

                            <button type="submit" class="btn btn-primary book-btn">
                                Book Now
                            </button>

                        </form>

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