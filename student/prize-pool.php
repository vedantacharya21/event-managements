<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

requireRole("student");

$event_id = (int) ($_GET["event_id"] ?? 0);

if ($event_id <= 0) {
    header("Location: /eventmanagements/index.php");
    exit();
}

// Fetch event
$stmt = $conn->prepare("
    SELECT event_id, title
    FROM events
    WHERE event_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Event query preparation failed: " . $conn->error);
}

$stmt->bind_param("i", $event_id);
$stmt->execute();

$result = $stmt->get_result();
$event = $result->fetch_assoc();

$stmt->close();

if (!$event) {
    exit("Event not found.");
}

/*
|--------------------------------------------------------------------------
| Fetch Prize Pool
|--------------------------------------------------------------------------
*/

$prizes = [];

$prize_sql = "
    SELECT prize_id, position, amount, description
    FROM prizes
    WHERE event_id = ?
    ORDER BY position ASC
";

$prize_stmt = $conn->prepare($prize_sql);

if (!$prize_stmt) {
    die("Prize query preparation failed: " . htmlspecialchars($conn->error, ENT_QUOTES, "UTF-8"));
}

$prize_stmt->bind_param("i", $event_id);

if (!$prize_stmt->execute()) {
    die("Prize query execution failed: " . htmlspecialchars($prize_stmt->error, ENT_QUOTES, "UTF-8"));
}

$prize_result = $prize_stmt->get_result();

while ($row = $prize_result->fetch_assoc()) {
    $prizes[] = $row;
}

$prize_stmt->close();

require_once __DIR__ . "/../includes/header.php";
?>

<div class="page-header">
    <h1>Prize Pool</h1>
    <p>
        Event:
        <?php echo htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8"); ?>
    </p>
</div>

<div class="card">

    <?php if (count($prizes) === 0): ?>

        <div class="empty-state">
            <h2>No Prizes Available</h2>
            <p>The organizer has not added any prizes yet.</p>
        </div>

    <?php else: ?>

        <div class="event-prize-list">
            <?php foreach ($prizes as $prize): ?>
                <div class="card">
                    <h3>Position <?php echo (int) $prize["position"]; ?></h3>
                    <p>
                        <strong>Prize Amount:</strong>
                        ₹<?php echo number_format((float) $prize["amount"], 2); ?>
                    </p>
                    <?php if (!empty($prize["description"])): ?>
                        <p>
                            <strong>Description:</strong>
                            <?php echo htmlspecialchars($prize["description"], ENT_QUOTES, "UTF-8"); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<a href="/eventmanagements/index.php" class="btn btn-secondary">Back to Events</a>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>