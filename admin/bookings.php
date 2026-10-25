<?php

require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../classes/Booking.php";

requireAdmin();

$database = new Database();
$conn = $database->connect();

$booking = new Booking($conn);

$message = "";
$messageType = "";

// Handle booking actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!validateCsrfToken()) {
        http_response_code(403);
        exit("Invalid CSRF token.");
    }

    $action = $_POST["action"] ?? "";

    if (!is_string($action)) {
        http_response_code(400);
        exit("Invalid action.");
    }

    // Mark booking as completed
    if ($action === "complete") {

        $bookingId = $_POST["booking_id"] ?? "";

        if (
            !is_string($bookingId) ||
            !filter_var($bookingId, FILTER_VALIDATE_INT) ||
            (int) $bookingId < 1
        ) {
            $message = "Invalid booking selected.";
            $messageType = "error";

        } else {

            $result = $booking->complete($bookingId);

            if ($result === true) {
                $_SESSION["flash_message"] = "Booking marked as completed successfully!";
                $_SESSION["flash_type"] = "success";

                header("Location: bookings.php");
                exit;
            }

            $message = "Unable to complete booking. It must be confirmed and its lesson must have ended.";
            $messageType = "error";
        }

    } else {
        $message = "Unknown action.";
        $messageType = "error";
    }
}

// Display flash message
if (isset($_SESSION["flash_message"])) {

    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"] ?? "error";

    unset($_SESSION["flash_message"]);
    unset($_SESSION["flash_type"]);
}

// Get booking status filter
$statusFilter = $_GET["status"] ?? "";

if (
    !is_string($statusFilter) ||
    !in_array($statusFilter, ["", "Confirmed", "Cancelled", "Completed"], true)
) {
    $statusFilter = "";
}

// Get all bookings
$allBookings = $booking->getAll();

// Filter bookings
$bookings = [];

foreach ($allBookings as $item) {
    if ($statusFilter === "" || $item["status"] === $statusFilter) {
        $bookings[] = $item;
    }
}

// Calculate booking counts
$totalBookings = count($allBookings);
$confirmedCount = 0;
$cancelledCount = 0;
$completedCount = 0;

foreach ($allBookings as $item) {

    if ($item["status"] === "Confirmed") {
        $confirmedCount++;
    } elseif ($item["status"] === "Cancelled") {
        $cancelledCount++;
    } elseif ($item["status"] === "Completed") {
        $completedCount++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Bookings | Driving Lesson Booking</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="dashboard-page admin-page admin-bookings-page">

    <header class="site-header">

        <nav class="navbar container" aria-label="Admin navigation">

            <a href="dashboard.php" class="navbar-brand">
                Driving Lesson Booking
            </a>

            <div class="navbar-links">

                <a href="dashboard.php" class="nav-link">
                    Dashboard
                </a>

                <a href="instructors.php" class="nav-link">
                    Instructors
                </a>

                <a href="lessons.php" class="nav-link">
                    Lessons
                </a>

                <a href="bookings.php" class="nav-link active" aria-current="page">
                    Bookings
                </a>

                <form action="../logout.php" method="POST" class="logout-form">

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

            <h1>Manage Bookings</h1>

            <p>
                Review driving lesson bookings and manage completed sessions.
            </p>

        </section>

        <?php if ($message !== ""): ?>

        <div class="message message-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <?php endif; ?>

        <section class="dashboard-stats" aria-labelledby="booking-stats-heading">

            <h2 id="booking-stats-heading">Booking Overview</h2>

            <div class="stats-grid">

                <article class="stat-card">
                    <h3>Total Bookings</h3>
                    <p class="stat-value"><?= $totalBookings ?></p>
                </article>

                <article class="stat-card">
                    <h3>Confirmed</h3>
                    <p class="stat-value"><?= $confirmedCount ?></p>
                </article>

                <article class="stat-card">
                    <h3>Cancelled</h3>
                    <p class="stat-value"><?= $cancelledCount ?></p>
                </article>

                <article class="stat-card">
                    <h3>Completed</h3>
                    <p class="stat-value"><?= $completedCount ?></p>
                </article>

            </div>

        </section>

        <section class="filter-section" aria-labelledby="filter-heading">

            <h2 id="filter-heading">Filter Bookings</h2>

            <form action="bookings.php" method="GET" class="filter-form">

                <div class="form-group">

                    <label for="status">
                        Booking Status
                    </label>

                    <select name="status" id="status" class="form-input">

                        <option value="" <?= $statusFilter === "" ? "selected" : "" ?>>
                            All Bookings
                        </option>

                        <option value="Confirmed" <?= $statusFilter === "Confirmed" ? "selected" : "" ?>>
                            Confirmed
                        </option>

                        <option value="Cancelled" <?= $statusFilter === "Cancelled" ? "selected" : "" ?>>
                            Cancelled
                        </option>

                        <option value="Completed" <?= $statusFilter === "Completed" ? "selected" : "" ?>>
                            Completed
                        </option>

                    </select>

                </div>

                <button type="submit" class="btn btn-primary">
                    Apply Filter
                </button>

                <a href="bookings.php" class="btn btn-outline">
                    Reset
                </a>

            </form>

        </section>

        <section class="table-section" aria-labelledby="bookings-heading">

            <div class="section-header">

                <h2 id="bookings-heading">All Bookings</h2>

                <p class="results-count">
                    <?= count($bookings) ?> booking(s)
                </p>

            </div>

            <?php if (empty($bookings)): ?>

            <div class="empty-state">

                <h3>No Bookings Found</h3>

                <p>
                    There are no bookings matching your selected filter.
                </p>

                <?php if ($statusFilter !== ""): ?>

                <a href="bookings.php" class="btn btn-primary">
                    View All Bookings
                </a>

                <?php endif; ?>

            </div>

            <?php else: ?>

            <div class="table-responsive">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th scope="col">Booking ID</th>
                            <th scope="col">Learner</th>
                            <th scope="col">Instructor</th>
                            <th scope="col">Lesson Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Transmission</th>
                            <th scope="col">Price</th>
                            <th scope="col">Booked On</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($bookings as $item): ?>

                        <?php

                                $lessonEnd = strtotime(
                                    $item["lesson_date"] . " " .
                                    $item["end_time"]
                                );

                                $canComplete = (
                                    $item["status"] === "Confirmed" &&
                                    $lessonEnd !== false &&
                                    $lessonEnd <= time()
                                );

                                ?>

                        <tr>

                            <td>
                                #<?= (int) $item["booking_id"] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($item["learner_name"], ENT_QUOTES, 'UTF-8') ?>
                                </strong>

                                <div class="table-secondary-text">
                                    <?= htmlspecialchars($item["learner_email"], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>

                            <td>
                                <?= htmlspecialchars($item["instructor_name"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item["lesson_date"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(substr($item["start_time"], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                                -
                                <?= htmlspecialchars(substr($item["end_time"], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item["transmission_type"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= number_format((float) $item["price"], 2) ?>
                                JOD
                            </td>

                            <td>
                                <?= htmlspecialchars($item["booking_date"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <span class="status-badge status-<?= strtolower($item["status"]) ?>">
                                    <?= htmlspecialchars($item["status"], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <td class="table-actions">

                                <?php if ($canComplete): ?>

                                <form action="bookings.php" method="POST" class="inline-form">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                                    <input type="hidden" name="action" value="complete">

                                    <input type="hidden" name="booking_id" value="<?= (int) $item["booking_id"] ?>">

                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Mark Completed
                                    </button>

                                </form>

                                <?php elseif ($item["status"] === "Confirmed"): ?>

                                <span class="table-note">
                                    Awaiting lesson completion
                                </span>

                                <?php else: ?>

                                <span class="table-note">
                                    No actions available
                                </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php endif; ?>

        </section>

    </main>

    <script src="../assets/js/main.js" defer></script>

</body>

</html>