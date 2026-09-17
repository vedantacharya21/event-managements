<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Check Login Status
|--------------------------------------------------------------------------
*/

function isLoggedIn()
{
    return isset($_SESSION["user_id"]) &&
           !empty($_SESSION["user_id"]);
}

/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: /eventmanagements/login.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| Require Specific Role
|--------------------------------------------------------------------------
*/

function requireRole($role)
{
    requireLogin();

    if (
        !isset($_SESSION["role"]) ||
        $_SESSION["role"] !== $role
    ) {
        header("Location: /eventmanagements/index.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| Redirect User Based on Role
|--------------------------------------------------------------------------
*/

function redirectByRole()
{
    if (!isset($_SESSION["role"])) {
        header("Location: /eventmanagements/index.php");
        exit();
    }

    switch ($_SESSION["role"]) {

        case "student":
            header(
                "Location: /eventmanagements/student/dashboard.php"
            );
            break;

        case "organizer":
            header(
                "Location: /eventmanagements/organizer/dashboard.php"
            );
            break;

        case "admin":
            header(
                "Location: /eventmanagements/admin/dashboard.php"
            );
            break;

        default:
            header("Location: /eventmanagements/index.php");
            break;
    }

    exit();
}

?>