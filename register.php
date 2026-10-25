<?php

require_once __DIR__ . "/config/auth.php";
require_once __DIR__ . "/config/Database.php";
require_once __DIR__ . "/classes/User.php";

// Redirect logged-in users
if (isset($_SESSION["user_id"])) {

    if (($_SESSION["role"] ?? "") === "Admin") {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: lessons.php");
    }

    exit;
}

$database = new Database();

$conn = $database->connect();

$user = new User($conn);

$message = "";
$messageType = "";

$fullName = "";
$email = "";
$phone = "";

// $_SERVER: This is a built-in variable in PHP—a "superglobal array"—that contains information about the current request and the server.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!validateCsrfToken()) {

        http_response_code(403);
        exit("Invalid CSRF token.");
    }

    $fullName = $_POST["full_name"] ?? "";
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";
    $phone = $_POST["phone"] ?? "";

    // Validate input types
    if (
        !is_string($fullName) ||
        !is_string($email) ||
        !is_string($password) ||
        !is_string($phone)
    ) {

        http_response_code(400);
        $message = "Invalid form data.";
        $messageType = "error";

        $fullName = "";
        $email = "";
        $phone = "";

    } else {

        $result = $user->register(
            $fullName,
            $email,
            $password,
            $phone
        );

        if ($result === true) {

            $message = "Registration successful! You can now login.";
            $messageType = "success";

            $fullName = "";
            $email = "";
            $phone = "";

        } else {

            $message = "Registration failed. Please check your information.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | Driving Lesson Booking</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-page register-page">

    <main class="auth-container">

        <div class="auth-card">

            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Register to start booking your driving lessons.</p>
            </div>

            <?php if (!empty($message)): ?>

            <div class="message message-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <?php endif; ?>

            <?php if ($messageType === "success"): ?>

            <div class="auth-links">
                <a href="login.php">Go to Login</a>
            </div>

            <?php else: ?>

            <form action="register.php" method="POST" class="auth-form">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="full_name">Full Name</label>

                    <input type="text" id="full_name" name="full_name" class="form-input"
                        placeholder="Enter your full name"
                        value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" maxlength="100"
                        autocomplete="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>

                    <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" maxlength="100"
                        autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="Enter your password" minlength="8" maxlength="255" autocomplete="new-password"
                        required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>

                    <input type="tel" id="phone" name="phone" class="form-input" placeholder="0791234567"
                        value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" maxlength="10"
                        pattern="07[789][0-9]{7}" autocomplete="tel" required>
                </div>

                <button type="submit" class="btn btn-primary auth-submit">
                    Create Account
                </button>

            </form>

            <div class="auth-links">
                <p>
                    Already have an account?
                    <a href="login.php">Login here</a>
                </p>
            </div>

            <?php endif; ?>

        </div>

    </main>

    <script src="assets/js/main.js" defer></script>

</body>

</html>