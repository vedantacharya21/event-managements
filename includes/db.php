<?php

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

$host = "localhost";
$username = "root";
$password = "";
$database = "event_management";

/*
|--------------------------------------------------------------------------
| Create Database Connection
|--------------------------------------------------------------------------
*/

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

/*
|--------------------------------------------------------------------------
| Check Database Connection
|--------------------------------------------------------------------------
*/

if ($conn->connect_error) {
    die(
        "Database connection failed: " .
        $conn->connect_error
    );
}

/*
|--------------------------------------------------------------------------
| Set Character Encoding
|--------------------------------------------------------------------------
*/

if (!$conn->set_charset("utf8mb4")) {
    die(
        "Error setting database character set: " .
        $conn->error
    );
}

?>