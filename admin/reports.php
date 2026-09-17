<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("admin");

$total_users = 0;
$total_students = 0;
$total_organizers = 0;
$total_events = 0;
$approved_events = 0;
$pending_events = 0;
$completed_events = 0;
$total_registrations = 0;
$total_feedback = 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($result) $total_users = (int) $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
if ($result) $total_students = (int) $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'organizer'");
if ($result) $total_organizers = (int) $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM events");
if ($result) $total_events = (int) $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM events WHERE status = 'approved'");
if ($result) $approved_events = (int) $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM events WHERE status = 'pending'");
if ($result) $pending_events = (int) $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM events WHERE status = 'completed'");
if ($result) $completed_events = (int) $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM registrations WHERE status = 'registered'");
if ($result) $total_registrations = (int) $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM feedback");
if ($result) $total_feedback = (int) $result->fetch_assoc()["total"];

$event_report = $conn->query("
    SELECT
        e.event_id,
        e.title,
        e.event_date,
        e.status,
        u.name AS organizer_name,
        (
            SELECT COUNT(*)
            FROM registrations r
            WHERE r.event_id = e.event_id
            AND r.status = 'registered'
        ) AS registered_count,
        (
            SELECT COUNT(*)
            FROM feedback f
            WHERE f.event_id = e.event_id
        ) AS feedback_count,
        (
            SELECT ROUND(AVG(f.rating), 1)
            FROM feedback f
            WHERE f.event_id = e.event_id
        ) AS average_rating
    FROM events e
    INNER JOIN users u ON u.user_id = e.organizer_id
    ORDER BY e.event_date DESC
");

if (!$event_report) {
    die("Event report query failed: " . $conn->error);
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="page-header">
    <h1>Reports</h1>
    <p>View overall portal statistics and event reports.</p>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-value"><?= $total_users ?></div><div class="stat-label">Total Users</div></div>
    <div class="stat-card"><div class="stat-value"><?= $total_students ?></div><div class="stat-label">Students</div></div>
    <div class="stat-card"><div class="stat-value"><?= $total_organizers ?></div><div class="stat-label">Organizers</div></div>
    <div class="stat-card"><div class="stat-value"><?= $total_events ?></div><div class="stat-label">Total Events</div></div>
    <div class="stat-card"><div class="stat-value"><?= $approved_events ?></div><div class="stat-label">Approved Events</div></div>
    <div class="stat-card"><div class="stat-value"><?= $pending_events ?></div><div class="stat-label">Pending Events</div></div>
    <div class="stat-card"><div class="stat-value"><?= $completed_events ?></div><div class="stat-label">Completed Events</div></div>
    <div class="stat-card"><div class="stat-value"><?= $total_registrations ?></div><div class="stat-label">Registrations</div></div>
    <div class="stat-card"><div class="stat-value"><?= $total_feedback ?></div><div class="stat-label">Feedback Entries</div></div>
</div>

<br>

<div class="card">
    <h2 class="section-title">Event-wise Report</h2>

    <?php if ($event_report->num_rows === 0): ?>

        <div class="empty-state">
            <h3>No Events Found</h3>
            <p>There are no events available for reporting.</p>
        </div>

    <?php else: ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Organizer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Registrations</th>
                        <th>Feedback</th>
                        <th>Average Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($event = $event_report->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($event["organizer_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars(date("d M Y", strtotime($event["event_date"])), ENT_QUOTES, "UTF-8") ?></td>
                            <td>
                                <span class="status status-<?= htmlspecialchars($event["status"], ENT_QUOTES, "UTF-8") ?>">
                                    <?= htmlspecialchars(ucfirst($event["status"]), ENT_QUOTES, "UTF-8") ?>
                                </span>
                            </td>
                            <td><?= (int) $event["registered_count"] ?></td>
                            <td><?= (int) $event["feedback_count"] ?></td>
                            <td>
                                <?php if ($event["average_rating"] !== null): ?>
                                    <?= number_format((float) $event["average_rating"], 1) ?> / 5 ⭐
                                <?php else: ?>
                                    No Rating
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<br>

<div class="action-buttons">
    <a href="/eventmanagements/admin/users.php" class="btn">Manage Users</a>
    <a href="/eventmanagements/admin/events.php" class="btn">Manage Events</a>
    <a href="/eventmanagements/admin/dashboard.php" class="btn">Admin Dashboard</a>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>