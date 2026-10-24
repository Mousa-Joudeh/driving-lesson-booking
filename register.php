<?php

session_start();

require_once __DIR__ . "/config/Database.php";
require_once __DIR__ . "/classes/User.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$database = new Database();

$conn = $database->connect();

$user = new User($conn);

$message = "";

// $_SERVER: This is a built-in variable in PHP—a "superglobal array"—that contains information about the current request and the server.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)) {

        http_response_code(403);
        exit("Invalid CSRF token.");
    }
    
    $fullName = $_POST["full_name"] ?? "";
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";
    $phone = $_POST["phone"] ?? "";

    $result = $user->register(
        $fullName,
        $email,
        $password,
        $phone
    );

    if ($result === true) {
        $message = "Registration successful!";
    } else {
        $message = "Registration failed. Please check your information.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Driving Lesson Booking</title>
</head>

<body>

    <h1>Create Account</h1>

    <?php if (!empty($message)): ?>

    <p>
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </p>

    <?php endif; ?>

    <form action="register.php" method="POST">
        <input type="hidden" name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div>
            <label for="full_name">Full Name</label>

            <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" required>
        </div>

        <div>
            <label for="email">Email Address</label>

            <input type="email" id="email" name="email" placeholder="Enter your email" required>
        </div>

        <div>
            <label for="password">Password</label>

            <input type="password" id="password" name="password" placeholder="Enter your password" minlength="8"
                autocomplete="new-password" required>
        </div>

        <div>
            <label for="phone">Phone Number</label>

            <input type="tel" id="phone" name="phone" placeholder="0791234567" maxlength="10" pattern="07[789][0-9]{7}"
                autocomplete="tel" required>
        </div>

        <button type="submit">Create Account</button>

    </form>

</body>

</html>