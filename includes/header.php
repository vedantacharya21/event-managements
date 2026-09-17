<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/auth.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        EventHub - College Event Management Portal
    </title>

    <link
        rel="stylesheet"
        href="/eventmanagements/assets/css/style.css"
    >

</head>

<body>

<header class="site-header">

    <div class="header-container">

        <a
            href="/eventmanagements/index.php"
            class="logo"
        >
            EventHub
        </a>

        <nav class="nav-links">

            <a href="/eventmanagements/index.php">
                Home
            </a>

            <?php if (isLoggedIn()): ?>

                <?php if ($_SESSION["role"] === "student"): ?>

                    <a href="/eventmanagements/student/dashboard.php">
                        Dashboard
                    </a>

                    <a href="/eventmanagements/student/my-events.php">
                        My Events
                    </a>

                    <a href="/eventmanagements/student/feedback.php">
                        Feedback
                    </a>

                <?php elseif ($_SESSION["role"] === "organizer"): ?>

                    <a href="/eventmanagements/organizer/dashboard.php">
                        Dashboard
                    </a>

                    <a href="/eventmanagements/organizer/create-event.php">
                        Create Event
                    </a>

                <?php elseif ($_SESSION["role"] === "admin"): ?>

                    <a href="/eventmanagements/admin/dashboard.php">
                        Dashboard
                    </a>

                    <a href="/eventmanagements/admin/users.php">
                        Users
                    </a>

                    <a href="/eventmanagements/admin/events.php">
                        Events
                    </a>

                    <a href="/eventmanagements/admin/reports.php">
                        Reports
                    </a>

                <?php endif; ?>

                <span class="user-info">

                    <?php
                    echo htmlspecialchars(
                        $_SESSION["name"] ?? "User",
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </span>

                <a
                    href="/eventmanagements/logout.php"
                    class="nav-logout"
                >
                    Logout
                </a>

            <?php else: ?>

                <a href="/eventmanagements/login.php">
                    Login
                </a>

                <a
                    href="/eventmanagements/register.php"
                    class="nav-register"
                >
                    Register
                </a>

            <?php endif; ?>

        </nav>

    </div>

</header>

<main class="main-content">

    <div class="container">