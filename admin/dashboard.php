<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("admin");

$stats = [];

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
$stats["users"] = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
$stats["students"] = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'organizer'");
$stats["organizers"] = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM events");
$stats["events"] = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM events WHERE status = 'pending'");
$stats["pending"] = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM events WHERE status = 'approved'");
$stats["approved"] = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM events WHERE status = 'completed'");
$stats["completed"] = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM registrations WHERE status = 'registered'");
$stats["registrations"] = $result->fetch_assoc()["total"];

$recent_events = $conn->query("
    SELECT
        e.event_id,
        e.title,
        e.event_date,
        e.status,
        u.name AS organizer_name
    FROM events e
    INNER JOIN users u ON u.user_id = e.organizer_id
    ORDER BY e.created_at DESC
    LIMIT 8
");

if (!$recent_events) {
    die("Recent events query failed: " . $conn->error);
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="dashboard-header">
    <h1>Admin Dashboard</h1>
    <p>
        Welcome,
        <strong>
            <?= htmlspecialchars($_SESSION["name"] ?? "Admin", ENT_QUOTES, "UTF-8") ?>
        </strong>
    </p>
</div>

<div class="stats-grid">
    <div class="stat-card"><h3>Total Users</h3><p><?= (int) $stats["users"] ?></p></div>
    <div class="stat-card"><h3>Students</h3><p><?= (int) $stats["students"] ?></p></div>
    <div class="stat-card"><h3>Organizers</h3><p><?= (int) $stats["organizers"] ?></p></div>
    <div class="stat-card"><h3>Total Events</h3><p><?= (int) $stats["events"] ?></p></div>
    <div class="stat-card"><h3>Pending Events</h3><p><?= (int) $stats["pending"] ?></p></div>
    <div class="stat-card"><h3>Approved Events</h3><p><?= (int) $stats["approved"] ?></p></div>
    <div class="stat-card"><h3>Completed Events</h3><p><?= (int) $stats["completed"] ?></p></div>
    <div class="stat-card"><h3>Registrations</h3><p><?= (int) $stats["registrations"] ?></p></div>
</div>

<hr>

<h2>Recent Events</h2>

<?php if ($recent_events->num_rows === 0): ?>

    <div class="empty-state"><p>No events available.</p></div>

<?php else: ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Organizer</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($event = $recent_events->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($event["organizer_name"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($event["event_date"], ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <span class="status status-<?= htmlspecialchars($event["status"], ENT_QUOTES, "UTF-8") ?>">
                                <?= htmlspecialchars(ucfirst($event["status"]), ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>
                        <td>
                            <a href="/eventmanagements/admin/events.php" class="btn btn-small">Manage</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<br>

<div class="action-buttons">
    <a href="/eventmanagements/admin/users.php" class="btn">Manage Users</a>
    <a href="/eventmanagements/admin/events.php" class="btn">Manage Events</a>
    <a href="/eventmanagements/admin/reports.php" class="btn">View Reports</a>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>