<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

requireRole("student");

$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Validate Event ID
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"]) ||
    (int) $_GET["id"] <= 0
) {
    header("Location: /eventmanagements/student/my-events.php");
    exit();
}

$event_id = (int) $_GET["id"];

/*
|--------------------------------------------------------------------------
| Find Student's Active Registration
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        r.registration_id,
        e.title
    FROM registrations r
    INNER JOIN events e
        ON r.event_id = e.event_id
    WHERE r.user_id = ?
      AND r.event_id = ?
      AND r.status = 'registered'
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $user_id,
    $event_id
);

if (!$stmt->execute()) {
    die("Failed to find registration: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: /eventmanagements/student/my-events.php");
    exit();
}

$registration = $result->fetch_assoc();

$stmt->close();

$registration_id = (int) $registration["registration_id"];

/*
|--------------------------------------------------------------------------
| Cancel Registration
|--------------------------------------------------------------------------
*/

$update_sql = "
    UPDATE registrations
    SET status = 'cancelled'
    WHERE registration_id = ?
      AND user_id = ?
      AND event_id = ?
      AND status = 'registered'
";

$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {
    die("Database error: " . $conn->error);
}

$update_stmt->bind_param(
    "iii",
    $registration_id,
    $user_id,
    $event_id
);

if (!$update_stmt->execute()) {
    die("Failed to cancel registration: " . $update_stmt->error);
}

$update_stmt->close();

/*
|--------------------------------------------------------------------------
| Redirect to My Events
|--------------------------------------------------------------------------
*/

header("Location: /eventmanagements/student/my-events.php");
exit();

?>