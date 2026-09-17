<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

requireRole("student");

$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Allow POST Requests Only
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: /eventmanagements/student/dashboard.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Validate Feedback ID
|--------------------------------------------------------------------------
*/

$feedback_id = (int) ($_POST["feedback_id"] ?? 0);

if ($feedback_id <= 0) {

    header(
        "Location: /eventmanagements/student/dashboard.php?error=invalid_feedback"
    );

    exit();

}

/*
|--------------------------------------------------------------------------
| Delete Feedback
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM feedback
    WHERE feedback_id = ?
      AND user_id = ?
");

if (!$stmt) {

    die("Database error: " . $conn->error);

}

$stmt->bind_param(
    "ii",
    $feedback_id,
    $user_id
);

if (!$stmt->execute()) {

    $stmt->close();

    die("Failed to delete feedback: " . $stmt->error);

}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    "Location: /eventmanagements/student/feedback.php?success=feedback_deleted"
);

exit();

?>