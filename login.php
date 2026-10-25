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
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!validateCsrfToken()) {
        http_response_code(403);
        exit("Invalid CSRF token.");
    }

    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";

    if (!is_string($email) || !is_string($password)) {
        http_response_code(400);
        exit("Invalid form data.");
    }

    $loggedInUser = $user->login($email, $password);

    if ($loggedInUser !== false) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $loggedInUser["user_id"];
        $_SESSION["user_name"] = $loggedInUser["full_name"];
        $_SESSION["role"] = $loggedInUser["role"];

        // Generate a new CSRF token after login
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

        if ($loggedInUser["role"] === "Admin") {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: lessons.php");
        }

        exit;

    } else {
        $message = "Invalid email or password.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Driving Lesson Booking</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-page login-page">

    <main class="auth-container">

        <div class="auth-card">

            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Login to book your driving lessons</p>
            </div>

            <?php if (!empty($message)): ?>

            <div class="message message-error" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <?php endif; ?>

            <form action="login.php" method="POST" class="auth-form">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="email">Email Address</label>

                    <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" maxlength="100"
                        autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="Enter your password" autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn btn-primary auth-submit">
                    Login
                </button>

            </form>

            <div class="auth-links">
                <p>
                    Don't have an account?
                    <a href="register.php">Register here</a>
                </p>
            </div>

        </div>

    </main>

    <script src="assets/js/main.js" defer></script>

</body>

</html>