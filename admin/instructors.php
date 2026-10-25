<?php

require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../classes/Instructor.php";

requireAdmin();

$database = new Database();
$conn = $database->connect();

$instructor = new Instructor($conn);

$message = "";
$messageType = "";

$editInstructor = false;

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

    // Add or update instructor
    if ($action === "add" || $action === "update") {

        $fullName = $_POST["full_name"] ?? "";
        $email = $_POST["email"] ?? "";
        $phone = $_POST["phone"] ?? "";
        $transmissionType = $_POST["transmission_type"] ?? "";

        if (
            !is_string($fullName) ||
            !is_string($email) ||
            !is_string($phone) ||
            !is_string($transmissionType)
        ) {
            $message = "Invalid form data.";
            $messageType = "error";

        } else {

            if ($action === "add") {

                $result = $instructor->add(
                    $fullName,
                    $email,
                    $phone,
                    $transmissionType
                );

                if ($result) {
                    $_SESSION["flash_message"] = "Instructor added successfully!";
                    $_SESSION["flash_type"] = "success";

                    header("Location: instructors.php");
                    exit;
                }

                $message = "Failed to add instructor. Check the information or email address.";
                $messageType = "error";

            } else {

                $id = $_POST["instructor_id"] ?? "";

                if (!is_string($id) || !filter_var($id, FILTER_VALIDATE_INT)) {
                    $message = "Invalid instructor ID.";
                    $messageType = "error";

                } else {

                    $result = $instructor->update(
                        $id,
                        $fullName,
                        $email,
                        $phone,
                        $transmissionType
                    );

                    if ($result) {
                        $_SESSION["flash_message"] = "Instructor updated successfully!";
                        $_SESSION["flash_type"] = "success";

                        header("Location: instructors.php");
                        exit;
                    }

                    $message = "Failed to update instructor. Check the information or future lesson conflicts.";
                    $messageType = "error";
                }
            }
        }
    }

    // Change instructor status
    elseif ($action === "change_status") {

        $id = $_POST["instructor_id"] ?? "";
        $status = $_POST["status"] ?? "";

        if (
            !is_string($id) ||
            !filter_var($id, FILTER_VALIDATE_INT) ||
            !is_string($status) ||
            !in_array($status, ["Active", "Inactive"], true)
        ) {
            $message = "Invalid instructor status request.";
            $messageType = "error";

        } else {

            $result = $instructor->updateStatus($id, $status);

            if ($result) {
                $_SESSION["flash_message"] = "Instructor status updated successfully!";
                $_SESSION["flash_type"] = "success";

                header("Location: instructors.php");
                exit;
            }

            $message = "Unable to change instructor status. Check for future scheduled lessons.";
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

// Load instructor for editing
$editId = $_GET["edit"] ?? "";

if (is_string($editId) && filter_var($editId, FILTER_VALIDATE_INT)) {

    $editInstructor = $instructor->getById($editId);

    if ($editInstructor === false) {
        $message = "Instructor not found.";
        $messageType = "error";
    }
}

// Get all instructors
$instructors = $instructor->getAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Instructors | Driving Lesson Booking</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="dashboard-page admin-page instructors-page">

    <header class="site-header">

        <nav class="navbar container" aria-label="Admin navigation">

            <a href="dashboard.php" class="navbar-brand">
                Driving Lesson Booking
            </a>

            <div class="navbar-links">

                <a href="dashboard.php" class="nav-link">Dashboard</a>

                <a href="instructors.php" class="nav-link active" aria-current="page">
                    Instructors
                </a>

                <a href="lessons.php" class="nav-link">Lessons</a>

                <a href="bookings.php" class="nav-link">Bookings</a>

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

            <h1>Manage Instructors</h1>

            <p>Add, update, and manage driving instructors.</p>

        </section>

        <?php if (!empty($message)): ?>

        <div class="message message-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <?php endif; ?>

        <section class="form-section" aria-labelledby="instructor-form-heading">

            <div class="section-header">

                <h2 id="instructor-form-heading">
                    <?= $editInstructor ? "Edit Instructor" : "Add New Instructor" ?>
                </h2>

                <?php if ($editInstructor): ?>
                <a href="instructors.php" class="btn btn-outline">
                    Cancel Edit
                </a>
                <?php endif; ?>

            </div>

            <form action="instructors.php" method="POST" class="admin-form">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                <input type="hidden" name="action" value="<?= $editInstructor ? "update" : "add" ?>">

                <?php if ($editInstructor): ?>

                <input type="hidden" name="instructor_id" value="<?= (int) $editInstructor["instructor_id"] ?>">

                <?php endif; ?>

                <div class="form-grid">

                    <div class="form-group">

                        <label for="full_name">Full Name</label>

                        <input type="text" id="full_name" name="full_name" class="form-input"
                            value="<?= htmlspecialchars($editInstructor["full_name"] ?? "", ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="100" autocomplete="name" required>

                    </div>

                    <div class="form-group">

                        <label for="email">Email Address</label>

                        <input type="email" id="email" name="email" class="form-input"
                            value="<?= htmlspecialchars($editInstructor["email"] ?? "", ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="150" autocomplete="email" required>

                    </div>

                    <div class="form-group">

                        <label for="phone">Phone Number</label>

                        <input type="tel" id="phone" name="phone" class="form-input"
                            value="<?= htmlspecialchars($editInstructor["phone"] ?? "", ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="10" pattern="07[789][0-9]{7}" placeholder="0791234567" autocomplete="tel"
                            required>

                    </div>

                    <div class="form-group">

                        <label for="transmission_type">Transmission Type</label>

                        <select id="transmission_type" name="transmission_type" class="form-input" required>

                            <option value="">Select Transmission Type</option>

                            <option value="Manual"
                                <?= ($editInstructor["transmission_type"] ?? "") === "Manual" ? "selected" : "" ?>>
                                Manual
                            </option>

                            <option value="Automatic"
                                <?= ($editInstructor["transmission_type"] ?? "") === "Automatic" ? "selected" : "" ?>>
                                Automatic
                            </option>

                            <option value="Both"
                                <?= ($editInstructor["transmission_type"] ?? "") === "Both" ? "selected" : "" ?>>
                                Both
                            </option>

                        </select>

                    </div>

                </div>

                <button type="submit" class="btn btn-primary">
                    <?= $editInstructor ? "Update Instructor" : "Add Instructor" ?>
                </button>

            </form>

        </section>

        <section class="table-section" aria-labelledby="instructors-heading">

            <div class="section-header">

                <h2 id="instructors-heading">All Instructors</h2>

                <p class="results-count">
                    <?= count($instructors) ?> instructor(s)
                </p>

            </div>

            <?php if (empty($instructors)): ?>

            <div class="empty-state">

                <h3>No Instructors Yet</h3>

                <p>Add your first instructor using the form above.</p>

            </div>

            <?php else: ?>

            <div class="table-responsive">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Transmission</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($instructors as $item): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($item["full_name"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item["email"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item["phone"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item["transmission_type"], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <span class="status-badge status-<?= strtolower($item["status"]) ?>">
                                    <?= htmlspecialchars($item["status"], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <td class="table-actions">

                                <a href="instructors.php?edit=<?= (int) $item["instructor_id"] ?>"
                                    class="btn btn-outline btn-sm">
                                    Edit
                                </a>

                                <form action="instructors.php" method="POST" class="inline-form">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                                    <input type="hidden" name="action" value="change_status">

                                    <input type="hidden" name="instructor_id"
                                        value="<?= (int) $item["instructor_id"] ?>">

                                    <input type="hidden" name="status"
                                        value="<?= $item["status"] === "Active" ? "Inactive" : "Active" ?>">

                                    <button type="submit"
                                        class="btn btn-sm <?= $item["status"] === "Active" ? "btn-danger" : "btn-primary" ?>">
                                        <?= $item["status"] === "Active" ? "Deactivate" : "Activate" ?>
                                    </button>

                                </form>

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