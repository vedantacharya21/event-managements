<?php

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

$event_id = (int) ($_GET["id"] ?? $_GET["event_id"] ?? 0);

if ($event_id <= 0) {
    header("Location: /eventmanagements/index.php");
    exit();
}

$sql = "
    SELECT
        e.event_id,
        e.title,
        e.description,
        e.poster,
        e.event_date,
        e.event_time,
        e.venue,
        e.capacity,
        e.registration_fee,
        e.event_type,
        e.status,
        e.organizer_id,
        u.name AS organizer_name,
        (
            SELECT COUNT(*)
            FROM registrations r
            WHERE r.event_id = e.event_id
              AND r.status = 'registered'
        ) AS registered_count
    FROM events e
    INNER JOIN users u ON e.organizer_id = u.user_id
    WHERE e.event_id = ?
      AND e.status IN ('approved', 'completed')
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query preparation failed: " . htmlspecialchars($conn->error, ENT_QUOTES, "UTF-8"));
}

$stmt->bind_param("i", $event_id);

if (!$stmt->execute()) {
    die("Database query execution failed: " . htmlspecialchars($stmt->error, ENT_QUOTES, "UTF-8"));
}

$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    header("Location: /eventmanagements/index.php");
    exit();
}

$registration = null;

if (isLoggedIn() && isset($_SESSION["role"]) && $_SESSION["role"] === "student") {

    $student_id = (int) $_SESSION["user_id"];

    $registration_stmt = $conn->prepare("
        SELECT registration_id, status, registered_at
        FROM registrations
        WHERE user_id = ? AND event_id = ?
        LIMIT 1
    ");

    if ($registration_stmt) {
        $registration_stmt->bind_param("ii", $student_id, $event_id);
        if ($registration_stmt->execute()) {
            $registration = $registration_stmt->get_result()->fetch_assoc();
        }
        $registration_stmt->close();
    }
}

$registered_count = (int) $event["registered_count"];
$capacity = (int) $event["capacity"];
$available_seats = max(0, $capacity - $registered_count);

$poster_name = "";
if (!empty($event["poster"])) {
    $poster_name = basename($event["poster"]);
}

$prizes = [];

$prize_stmt = $conn->prepare("
    SELECT prize_id, position, amount, description
    FROM prizes
    WHERE event_id = ?
    ORDER BY position ASC
");

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

require_once __DIR__ . "/includes/header.php";
?>

<div class="dashboard-header">
    <div>
        <h2><?php echo htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8"); ?></h2>
        <p><a href="/eventmanagements/index.php">&larr; Back to Events</a></p>
    </div>
</div>

<div class="event-details">
    <div class="event-detail-card">

        <p>
            <strong>Status:</strong>
            <?php if ($event["status"] === "completed"): ?>
                <span class="status status-completed">Completed</span>
            <?php else: ?>
                <span class="status status-approved">Approved</span>
            <?php endif; ?>
        </p>

        <?php if ($poster_name !== ""): ?>
            <div class="event-poster">
                <img
                    src="/eventmanagements/uploads/posters/<?php echo rawurlencode($poster_name); ?>"
                    alt="<?php echo htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8"); ?>"
                    width="400"
                >
            </div>
        <?php endif; ?>

        <section>
            <h3>Event Description</h3>
            <p><?php echo nl2br(htmlspecialchars($event["description"] ?? "", ENT_QUOTES, "UTF-8")); ?></p>
        </section>

        <section>
            <h3>Event Information</h3>
            <p><strong>Date:</strong> <?php echo date("d M Y", strtotime($event["event_date"])); ?></p>
            <p><strong>Time:</strong> <?php echo date("h:i A", strtotime($event["event_time"])); ?></p>
            <p><strong>Venue:</strong> <?php echo htmlspecialchars($event["venue"], ENT_QUOTES, "UTF-8"); ?></p>
            <p><strong>Event Type:</strong> <?php echo htmlspecialchars(ucfirst($event["event_type"]), ENT_QUOTES, "UTF-8"); ?></p>
            <p><strong>Organizer:</strong> <?php echo htmlspecialchars($event["organizer_name"], ENT_QUOTES, "UTF-8"); ?></p>
        </section>

        <section>
            <h3>Registration Information</h3>

            <p>
                <strong>Registration Fee:</strong>
                <?php if ((float) $event["registration_fee"] <= 0): ?>
                    Free
                <?php else: ?>
                    ₹<?php echo number_format((float) $event["registration_fee"], 2); ?>
                <?php endif; ?>
            </p>

            <p><strong>Registered:</strong> <?php echo $registered_count; ?> / <?php echo $capacity; ?></p>
            <p><strong>Available Seats:</strong> <?php echo $available_seats; ?></p>

            <?php if ((float) $event["registration_fee"] > 0): ?>
                <p class="alert">
                    The registration fee is recorded for reporting.
                    Online payment is not available.
                </p>
            <?php endif; ?>
        </section>

        <section>
            <h3>Prize Pool</h3>

            <?php if (count($prizes) === 0): ?>
                <div class="empty-state">
                    <p>Prize details have not been announced yet.</p>
                </div>
            <?php else: ?>
                <div class="event-prize-list">
                    <?php foreach ($prizes as $prize): ?>
                        <div class="card">
                            <h4>Position <?php echo (int) $prize["position"]; ?></h4>
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
        </section>

        <section>
            <h3>Registration</h3>

            <?php if ($event["status"] === "completed"): ?>

                <p>Registration for this event is closed.</p>

                <?php if (
                    isLoggedIn() &&
                    isset($_SESSION["role"]) &&
                    $_SESSION["role"] === "student" &&
                    $registration &&
                    $registration["status"] === "registered"
                ): ?>
                    <p>You were registered for this event.</p>
                    <a class="btn" href="/eventmanagements/student/feedback.php">Give Feedback</a>
                <?php endif; ?>

            <?php elseif (!isLoggedIn()): ?>

                <p>Please log in as a student to register for this event.</p>
                <a class="btn" href="/eventmanagements/login.php">Login to Register</a>

            <?php elseif (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student"): ?>

                <p>Only students can register for events.</p>

            <?php elseif ($registration && $registration["status"] === "registered"): ?>

                <p><strong>You are registered for this event.</strong></p>
                <a
                    class="btn btn-danger"
                    href="/eventmanagements/student/cancel-registration.php?id=<?php echo $event_id; ?>"
                    data-confirm="Are you sure you want to cancel your registration?"
                >
                    Cancel Registration
                </a>

            <?php elseif ($available_seats <= 0): ?>

                <p><strong>This event is full.</strong></p>

            <?php else: ?>

                <form method="POST" action="/eventmanagements/student/register-event.php">
                    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                    <button type="submit" class="btn">Register for Event</button>
                </form>

            <?php endif; ?>

        </section>

    </div>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>