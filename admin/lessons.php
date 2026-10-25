<?php

require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../classes/Instructor.php";
require_once __DIR__ . "/../classes/Lesson.php";

requireAdmin();

$database = new Database();
$conn = $database->connect();

$instructor = new Instructor($conn);
$lesson = new Lesson($conn);

$message = "";
$messageType = "";

$editLesson = false;

// Get all active instructors
$activeInstructors = $instructor->getActive();

// Handle form submissions
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

    // Add or update lesson
    if ($action === "add" || $action === "update") {

        $instructorId = $_POST["instructor_id"] ?? "";
        $lessonDate = $_POST["lesson_date"] ?? "";
        $transmissionType = $_POST["transmission_type"] ?? "";
        $startTime = $_POST["start_time"] ?? "";
        $endTime = $_POST["end_time"] ?? "";
        $price = $_POST["price"] ?? "";

        if (
            !is_string($instructorId) ||
            !is_string($lessonDate) ||
            !is_string($transmissionType) ||
            !is_string($startTime) ||
            !is_string($endTime) ||
            !is_string($price)
        ) {
            http_response_code(400);
            $message = "Invalid form data.";
            $messageType = "error";

        } elseif ($action === "add") {

            $result = $lesson->add(
                $instructorId,
                $lessonDate,
                $transmissionType,
                $startTime,
                $endTime,
                $price
            );

            if ($result === true) {

                $_SESSION["flash_message"] = "Lesson added successfully!";
                $_SESSION["flash_type"] = "success";

                header("Location: lessons.php");
                exit;
            }

            $message = "Failed to add lesson. Check the instructor, date, time, price, or scheduling conflicts.";
            $messageType = "error";

        } else {

            $lessonId = $_POST["lesson_id"] ?? "";

            if (
                !is_string($lessonId) ||
                !filter_var($lessonId, FILTER_VALIDATE_INT)
            ) {
                $message = "Invalid lesson ID.";
                $messageType = "error";

            } else {

                $result = $lesson->update(
                    $lessonId,
                    $instructorId,
                    $lessonDate,
                    $transmissionType,
                    $startTime,
                    $endTime,
                    $price
                );

                if ($result === true) {

                    $_SESSION["flash_message"] = "Lesson updated successfully!";
                    $_SESSION["flash_type"] = "success";

                    header("Location: lessons.php");
                    exit;
                }

                $message = "Failed to update lesson. It may be booked, cancelled, unchanged, or have a scheduling conflict.";
                $messageType = "error";
            }
        }
    }

    // Cancel a lesson
    elseif ($action === "cancel") {

        $lessonId = $_POST["lesson_id"] ?? "";

        if (
            !is_string($lessonId) ||
            !filter_var($lessonId, FILTER_VALIDATE_INT)
        ) {
            $message = "Invalid lesson selected.";
            $messageType = "error";

        } else {

            $result = $lesson->cancel($lessonId);

            if ($result === true) {

                $_SESSION["flash_message"] = "Lesson cancelled successfully!";
                $_SESSION["flash_type"] = "success";

                header("Location: lessons.php");
                exit;
            }

            $message = "Unable to cancel lesson. It may already be cancelled or have a confirmed booking.";
            $messageType = "error";
        }
    }

    else {
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

// Load lesson for editing
$editId = $_GET["edit"] ?? "";

if (
    is_string($editId) &&
    filter_var($editId, FILTER_VALIDATE_INT)
) {

    $editLesson = $lesson->getById($editId);

    if ($editLesson === false) {
        $message = "Lesson not found.";
        $messageType = "error";
    }

} elseif ($editId !== "") {

    $message = "Invalid lesson ID.";
    $messageType = "error";
}

// Get all lessons
$lessons = $lesson->getAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Lessons | Driving Lesson Booking</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="dashboard-page admin-page admin-lessons-page">

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

                <a href="lessons.php" class="nav-link active" aria-current="page">
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

            <h1>Manage Lessons</h1>

            <p>
                Create, update, and manage driving lesson sessions.
            </p>

        </section>

        <?php if ($message !== ""): ?>

        <div class="message message-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <?php endif; ?>

        <section class="form-section" aria-labelledby="lesson-form-heading">

            <div class="section-header">

                <h2 id="lesson-form-heading">
                    <?= $editLesson ? "Edit Lesson" : "Add New Lesson" ?>
                </h2>

                <?php if ($editLesson): ?>

                <a href="lessons.php" class="btn btn-outline">
                    Cancel Edit
                </a>

                <?php endif; ?>

            </div>

            <p class="form-description">
                Each driving lesson must be 60 minutes long.
                Lessons cannot overlap for the same instructor.
            </p>

            <?php if (empty($activeInstructors)): ?>

            <div class="empty-state">

                <h3>No Active Instructors</h3>

                <p>
                    You need at least one active instructor
                    before creating a driving lesson.
                </p>

                <a href="instructors.php" class="btn btn-primary">
                    Manage Instructors
                </a>

            </div>

            <?php else: ?>

            <form action="lessons.php" method="POST" class="admin-form">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                <input type="hidden" name="action" value="<?= $editLesson ? "update" : "add" ?>">

                <?php if ($editLesson): ?>

                <input type="hidden" name="lesson_id" value="<?= (int) $editLesson["lesson_id"] ?>">

                <?php endif; ?>

                <div class="form-grid">

                    <div class="form-group">

                        <label for="instructor_id">
                            Instructor
                        </label>

                        <select id="instructor_id" name="instructor_id" class="form-input" required>

                            <option value="">
                                Select Instructor
                            </option>

                            <?php foreach ($activeInstructors as $item): ?>

                            <option value="<?= (int) $item["instructor_id"] ?>"
                                <?= (string) ($editLesson["instructor_id"] ?? "") === (string) $item["instructor_id"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($item["full_name"], ENT_QUOTES, 'UTF-8') ?>
                                -
                                <?= htmlspecialchars($item["transmission_type"], ENT_QUOTES, 'UTF-8') ?>
                            </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="lesson_date">
                            Lesson Date
                        </label>

                        <input type="date" id="lesson_date" name="lesson_date" class="form-input"
                            value="<?= htmlspecialchars($editLesson["lesson_date"] ?? "", ENT_QUOTES, 'UTF-8') ?>"
                            required>

                    </div>

                    <div class="form-group">

                        <label for="transmission_type">
                            Transmission Type
                        </label>

                        <select id="transmission_type" name="transmission_type" class="form-input" required>

                            <option value="">
                                Select Transmission Type
                            </option>

                            <option value="Manual"
                                <?= ($editLesson["transmission_type"] ?? "") === "Manual" ? "selected" : "" ?>>
                                Manual
                            </option>

                            <option value="Automatic"
                                <?= ($editLesson["transmission_type"] ?? "") === "Automatic" ? "selected" : "" ?>>
                                Automatic
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="start_time">
                            Start Time
                        </label>

                        <input type="time" id="start_time" name="start_time" class="form-input"
                            value="<?= htmlspecialchars(substr($editLesson["start_time"] ?? "", 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                            step="60" required>

                    </div>

                    <div class="form-group">

                        <label for="end_time">
                            End Time
                        </label>

                        <input type="time" id="end_time" name="end_time" class="form-input"
                            value="<?= htmlspecialchars(substr($editLesson["end_time"] ?? "", 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                            step="60" required>

                    </div>

                    <div class="form-group">

                        <label for="price">
                            Lesson Price (JOD)
                        </label>

                        <input type="number" id="price" name="price" class="form-input" placeholder="Enter lesson price"
                            value="<?= htmlspecialchars($editLesson["price"] ?? "", ENT_QUOTES, 'UTF-8') ?>" min="0.01"
                            max="999999.99" step="0.01" required>

                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn btn-primary">
                        <?= $editLesson ? "Update Lesson" : "Add Lesson" ?>
                    </button>

                    <?php if ($editLesson): ?>

                    <a href="lessons.php" class="btn btn-outline">
                        Cancel
                    </a>

                    <?php endif; ?>

                </div>

            </form>

            <?php endif; ?>

        </section>

        <section class="table-section" aria-labelledby="lessons-heading">

            <div class="section-header">

                <h2 id="lessons-heading">
                    All Lessons
                </h2>

                <p class="results-count">
                    <?= count($lessons) ?> lesson(s)
                </p>

            </div>

            <?php if (empty($lessons)): ?>

            <div class="empty-state">

                <h3>No Lessons Yet</h3>

                <p>
                    Add your first driving lesson
                    using the form above.
                </p>

            </div>

            <?php else: ?>

            <div class="table-responsive">

                <table class="data-table">

                    <thead>

                        <tr>
                            <th scope="col">Instructor</th>
                            <th scope="col">Date</th>
                            <th scope="col">Start</th>
                            <th scope="col">End</th>
                            <th scope="col">Transmission</th>
                            <th scope="col">Price</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($lessons as $item): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($item["instructor_name"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item["lesson_date"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(substr($item["start_time"], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
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

                                <span class="status-badge status-<?= strtolower($item["status"]) ?>">
                                    <?= htmlspecialchars($item["status"], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                            </td>

                            <td class="table-actions">

                                <?php if ($item["status"] === "Scheduled"): ?>

                                <a href="lessons.php?edit=<?= (int) $item["lesson_id"] ?>"
                                    class="btn btn-outline btn-sm">
                                    Edit
                                </a>

                                <form action="lessons.php" method="POST" class="inline-form">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                                    <input type="hidden" name="action" value="cancel">

                                    <input type="hidden" name="lesson_id" value="<?= (int) $item["lesson_id"] ?>">

                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Cancel Lesson
                                    </button>

                                </form>

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