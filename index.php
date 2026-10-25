<?php

require_once __DIR__ . "/config/auth.php";

$isLoggedIn = isset($_SESSION["user_id"]);
$isAdmin = ($_SESSION["role"] ?? "") === "Admin";

$dashboardUrl = $isAdmin
    ? "admin/dashboard.php"
    : "lessons.php";

$userName = $_SESSION["user_name"] ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description"
        content="Book your driving lessons online. Choose your preferred transmission type, view available lessons, and manage your bookings.">

    <title>Driving Lesson Booking | Home</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="home-page">

    <header class="site-header">

        <nav class="navbar container" aria-label="Main navigation">

            <a href="index.php" class="navbar-brand">
                Driving Lesson Booking
            </a>

            <div class="navbar-links">

                <a href="index.php" class="nav-link active" aria-current="page">
                    Home
                </a>

                <?php if ($isLoggedIn): ?>

                <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <?= $isAdmin ? "Admin Dashboard" : "Available Lessons" ?>
                </a>

                <?php if (!$isAdmin): ?>

                <a href="my-bookings.php" class="nav-link">
                    My Bookings
                </a>

                <?php endif; ?>

                <form action="logout.php" method="POST" class="logout-form">

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

                    <button type="submit" class="btn btn-outline">
                        Logout
                    </button>

                </form>

                <?php else: ?>

                <a href="login.php" class="nav-link">
                    Login
                </a>

                <a href="register.php" class="btn btn-primary">
                    Create Account
                </a>

                <?php endif; ?>

            </div>

        </nav>

    </header>

    <main>

        <section class="hero-section">

            <div class="container hero-container">

                <div class="hero-content">

                    <?php if ($isLoggedIn): ?>

                    <p class="hero-label">
                        Welcome back,
                        <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>!
                    </p>

                    <?php else: ?>

                    <p class="hero-label">
                        Your Driving Journey Starts Here
                    </p>

                    <?php endif; ?>

                    <h1>
                        Learn to Drive with Confidence
                    </h1>

                    <p class="hero-description">
                        Find available driving lessons, choose between
                        manual and automatic transmission, and manage
                        your bookings in one place.
                    </p>

                    <div class="hero-actions">

                        <?php if ($isLoggedIn): ?>

                        <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">
                            <?= $isAdmin ? "Go to Dashboard" : "Browse Lessons" ?>
                        </a>

                        <?php else: ?>

                        <a href="register.php" class="btn btn-primary">
                            Get Started
                        </a>

                        <a href="login.php" class="btn btn-outline">
                            Login
                        </a>

                        <?php endif; ?>

                    </div>

                </div>

                <div class="hero-visual">

                    <div class="hero-visual-card">

                        <span class="hero-visual-icon" aria-hidden="true">
                            🚗
                        </span>

                        <h2>Ready for Your Next Lesson?</h2>

                        <p>
                            Choose a lesson that suits your schedule.
                        </p>

                    </div>

                </div>

            </div>

        </section>

        <section class="features-section" aria-labelledby="features-heading">

            <div class="container">

                <div class="section-header">

                    <h2 id="features-heading">
                        Why Book With Us?
                    </h2>

                    <p>
                        A simple way to organize your driving lessons.
                    </p>

                </div>

                <div class="features-grid">

                    <article class="feature-card">

                        <div class="feature-icon" aria-hidden="true">
                            📅
                        </div>

                        <h3>Easy Booking</h3>

                        <p>
                            Browse available lesson times and book your
                            preferred session online.
                        </p>

                    </article>

                    <article class="feature-card">

                        <div class="feature-icon" aria-hidden="true">
                            🚘
                        </div>

                        <h3>Manual or Automatic</h3>

                        <p>
                            Choose the transmission type that matches
                            your learning needs.
                        </p>

                    </article>

                    <article class="feature-card">

                        <div class="feature-icon" aria-hidden="true">
                            <icon name="clipboard-list" />
                        </div>

                        <h3>Manage Your Bookings</h3>

                        <p>
                            Review your lessons and cancel eligible
                            bookings directly from your account.
                        </p>

                    </article>

                </div>

            </div>

        </section>

        <section class="steps-section" aria-labelledby="steps-heading">

            <div class="container">

                <div class="section-header">

                    <h2 id="steps-heading">
                        How It Works
                    </h2>

                    <p>
                        Book your driving lesson in three simple steps.
                    </p>

                </div>

                <div class="steps-grid">

                    <article class="step-card">

                        <span class="step-number">01</span>

                        <h3>Create an Account</h3>

                        <p>
                            Register as a learner to access available
                            driving lessons.
                        </p>

                    </article>

                    <article class="step-card">

                        <span class="step-number">02</span>

                        <h3>Choose Your Lesson</h3>

                        <p>
                            Select a date, time, instructor, and
                            transmission type that suits you.
                        </p>

                    </article>

                    <article class="step-card">

                        <span class="step-number">03</span>

                        <h3>Confirm Your Booking</h3>

                        <p>
                            Book your lesson and manage it through
                            your account.
                        </p>

                    </article>

                </div>

            </div>

        </section>

        <section class="cta-section">

            <div class="container">

                <div class="cta-content">

                    <h2>Ready to Start Driving?</h2>

                    <p>
                        Take the next step and book your driving lesson today.
                    </p>

                    <?php if ($isLoggedIn): ?>

                    <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">
                        <?= $isAdmin ? "Open Dashboard" : "Explore Lessons" ?>
                    </a>

                    <?php else: ?>

                    <a href="register.php" class="btn btn-primary">
                        Create Your Account
                    </a>

                    <?php endif; ?>

                </div>

            </div>

        </section>

    </main>

    <footer class="site-footer">

        <div class="container footer-content">

            <div class="footer-brand">

                <h2>Driving Lesson Booking</h2>

                <p>
                    Making driving lesson booking simple and convenient.
                </p>

            </div>

            <nav class="footer-links" aria-label="Footer navigation">

                <a href="index.php">Home</a>

                <?php if ($isLoggedIn): ?>

                <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <?= $isAdmin ? "Dashboard" : "Available Lessons" ?>
                </a>

                <?php else: ?>

                <a href="login.php">Login</a>
                <a href="register.php">Register</a>

                <?php endif; ?>

            </nav>

        </div>

        <div class="container footer-bottom">

            <p>
                &copy; <?= date("Y") ?> Driving Lesson Booking.
                All rights reserved.
            </p>

        </div>

    </footer>
    <script src="assets/js/main.js" defer></script>

</body>

</html>