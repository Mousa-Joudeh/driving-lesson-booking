<?php

require_once __DIR__ . "/../config/auth.php";

requireAdmin();

require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../classes/Instructor.php";
require_once __DIR__ . "/../classes/Lesson.php";
require_once __DIR__ . "/../classes/Booking.php";

$database = new Database();
$conn = $database->connect();

$instructor = new Instructor($conn);
$lesson = new Lesson($conn);
$booking = new Booking($conn);

// Get dashboard data
$instructors = $instructor->getAll();
$lessons = $lesson->getAll();
$availableLessons = $lesson->getAvailableLessons();
$bookings = $booking->getAll();

// Calculate dashboard statistics
$totalInstructors = count($instructors);
$activeInstructors = 0;

foreach ($instructors as $item) {
    if ($item["status"] === "Active") {
        $activeInstructors++;
    }
}

$totalLessons = count($lessons);
$totalAvailableLessons = count($availableLessons);

$totalBookings = count($bookings);
$confirmedBookings = 0;

foreach ($bookings as $item) {
    if ($item["status"] === "Confirmed") {
        $confirmedBookings++;
    }
}

// Get 5 most recent bookings
$recentBookings = array_slice($bookings, 0, 5);

$adminName = $_SESSION["user_name"] ?? "Admin";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Driving Lesson Booking</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="dashboard-page admin-page">

    <header class="site-header">

        <nav class="navbar container" aria-label="Admin navigation">

            <a href="dashboard.php" class="navbar-brand">
                Driving Lesson Booking
            </a>

            <div class="navbar-links">

                <a href="dashboard.php" class="nav-link active" aria-current="page">
                    Dashboard
                </a>

                <a href="instructors.php" class="nav-link">
                    Instructors
                </a>

                <a href="lessons.php" class="nav-link">
                    Lessons
                </a>

                <a href="bookings.php" class="nav-link">
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

            <h1>Admin Dashboard</h1>

            <p>
                Welcome,
                <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>!
                Manage your driving school operations.
            </p>

        </section>

        <section class="dashboard-stats" aria-labelledby="stats-heading">

            <h2 id="stats-heading">Overview</h2>

            <div class="stats-grid">

                <article class="stat-card">
                    <h3>Total Instructors</h3>
                    <p class="stat-value"><?= $totalInstructors ?></p>
                </article>

                <article class="stat-card">
                    <h3>Active Instructors</h3>
                    <p class="stat-value"><?= $activeInstructors ?></p>
                </article>

                <article class="stat-card">
                    <h3>Total Lessons</h3>
                    <p class="stat-value"><?= $totalLessons ?></p>
                </article>

                <article class="stat-card">
                    <h3>Available Lessons</h3>
                    <p class="stat-value"><?= $totalAvailableLessons ?></p>
                </article>

                <article class="stat-card">
                    <h3>Total Bookings</h3>
                    <p class="stat-value"><?= $totalBookings ?></p>
                </article>

                <article class="stat-card">
                    <h3>Confirmed Bookings</h3>
                    <p class="stat-value"><?= $confirmedBookings ?></p>
                </article>

            </div>

        </section>

        <section class="dashboard-actions" aria-labelledby="actions-heading">

            <h2 id="actions-heading">Quick Actions</h2>

            <div class="actions-grid">

                <a href="instructors.php" class="action-card">
                    <h3>Manage Instructors</h3>
                    <p>Add, update, and manage driving instructors.</p>
                </a>

                <a href="lessons.php" class="action-card">
                    <h3>Manage Lessons</h3>
                    <p>Create and manage driving lesson sessions.</p>
                </a>

                <a href="bookings.php" class="action-card">
                    <h3>Manage Bookings</h3>
                    <p>Review and complete learner bookings.</p>
                </a>

            </div>

        </section>

        <section class="recent-bookings-section" aria-labelledby="recent-heading">

            <div class="section-header">
                <h2 id="recent-heading">Recent Bookings</h2>

                <a href="bookings.php" class="btn btn-outline">
                    View All Bookings
                </a>
            </div>

            <?php if (empty($recentBookings)): ?>

            <div class="empty-state">
                <h3>No Bookings Yet</h3>
                <p>New bookings will appear here.</p>
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
                            <th scope="col">Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentBookings as $recentBooking): ?>

                        <tr>
                            <td>
                                #<?= (int) $recentBooking["booking_id"] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($recentBooking["learner_name"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($recentBooking["instructor_name"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($recentBooking["lesson_date"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(substr($recentBooking["start_time"], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <span
                                    class="status-badge status-<?= strtolower(htmlspecialchars($recentBooking["status"], ENT_QUOTES, 'UTF-8')) ?>">
                                    <?= htmlspecialchars($recentBooking["status"], ENT_QUOTES, 'UTF-8') ?>
                                </span>
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