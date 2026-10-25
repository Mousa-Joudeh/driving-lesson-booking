<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF Token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Check if user is logged in
function requireLogin(): void
{
    if (!isset($_SESSION["user_id"])) {
        header("Location: /driving-lesson-booking/login.php");
        exit;
    }
}

// Check if user is an admin
function requireAdmin(): void
{
    requireLogin();

    if (($_SESSION["role"] ?? "") !== "Admin") {
        http_response_code(403);
        exit("Access denied.");
    }
}

// Validate CSRF Token
function validateCsrfToken(): bool
{
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token)) {
        return false;
    }

    return hash_equals($_SESSION["csrf_token"], $token);
}