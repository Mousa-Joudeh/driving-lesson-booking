<?php

require_once __DIR__ . "/config/auth.php";

// Allow POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("Method Not Allowed");
}

// Validate CSRF token
if (!validateCsrfToken()) {
    http_response_code(403);
    exit("Invalid CSRF token.");
}

// Clear session variables
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 3600,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => $params["samesite"] ?? "Lax"
        ]
    );
}

// Destroy session data
session_destroy();

// Redirect to login
header("Location: login.php");
exit;