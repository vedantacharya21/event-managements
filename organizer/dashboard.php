<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("organizer");

$organizer_id = (int) $_SESSION["user_id"];

$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_events,
        COALESCE(SUM(status = 'pending'), 0) AS pending_events,
        COALESCE(SUM(status = 'approved'), 0) AS approved_events,
        COALESCE(SUM(status = 'completed'), 0) AS completed_events,
        COALESCE(SUM(status = 'rejected'), 0) AS rejected_events
    FROM events
    WHERE organizer_id = ?
");

if (!$stats_stmt) die("Statistics query failed: " . $conn->error);

$stats_stmt->bind_param("i", $organizer_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

$stats = $stats ?: [
    "total_events" => 0, "pending_events" => 0, "approved_events" => 0,
    "completed_events" => 0, "rejected_events" => 0
];

$participants_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM registrations r
    INNER JOIN events e ON r.event_id = e.event_id
    WHERE e.organizer_id = ? AND r.status = 'registered'
");

if (!$participants_stmt) die("Participants query failed: " . $conn->error);

$participants_stmt->bind_param("i", $organizer_id);
$participants_stmt->execute();
$total_participants = (int) ($participants_stmt->get_result()->fetch_assoc()["total"] ?? 0);
$participants_stmt->close();

$events_stmt = $conn->prepare("
    SELECT
        e.event_id, e.title, e.event_date, e.event_time, e.venue,
        e.capacity, e.registration_fee, e.event_type, e.status, e.created_at,
        (
            SELECT COUNT(*) FROM registrations r
            WHERE r.event_id = e.event_id AND r.status = 'registered'
        ) AS registered_count
    FROM events e
    WHERE e.organizer_id = ?
    ORDER BY e.created_at DESC
");

if (!$events_stmt) die("Events query failed: " . $conn->error);

$events_stmt->bind_param("i", $organizer_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

require_once __DIR__ . "/../includes/header.php";
?>

<div class="dashboard-header">
    <div>
        <h1>Organizer Dashboard</h1>
        <p>Welcome, <strong><?= htmlspecialchars($_SESSION["name"] ?? "Organizer", ENT_QUOTES, "UTF-8") ?></strong></p>
    </div>
    <div>
        <a href="/eventmanagements/organizer/create-event.php" class="btn">+ Create Event</a>
    </div>
</div>

<section>
    <h2>Event Statistics</h2>
    <div class="stats-grid">
        <div class="stat-card"><h3>Total Events</h3><p><?= (int) $stats["total_events"] ?></p></div>
        <div class="stat-card"><h3>Pending</h3><p><?= (int) $stats["pending_events"] ?></p></div>
        <div class="stat-card"><h3>Approved</h3><p><?= (int) $stats["approved_events"] ?></p></div>
        <div class="stat-card"><h3>Completed</h3><p><?= (int) $stats["completed_events"] ?></p></div>
        <div class="stat-card"><h3>Rejected</h3><p><?= (int) $stats["rejected_events"] ?></p></div>
        <div class="stat-card"><h3>Registered Participants</h3><p><?= $total_participants ?></p></div>
    </div>
</section>

<br>

<section>
    <div class="dashboard-header"><h2>My Events</h2></div>

    <?php if ($events_result->num_rows === 0): ?>

        <div class="empty-state">
            <p>You have not created any events yet.</p>
            <a href="/eventmanagements/organizer/create-event.php" class="btn">Create Your First Event</a>
        </div>

    <?php else: ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Venue</th>
                        <th>Type</th>
                        <th>Fee</th>
                        <th>Participants</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($event = $events_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($event["event_date"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($event["event_time"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($event["venue"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars(ucfirst($event["event_type"]), ENT_QUOTES, "UTF-8") ?></td>
                            <td>
                                <?php if ((float) $event["registration_fee"] == 0): ?>
                                    Free
                                <?php else: ?>
                                    ₹<?= number_format((float) $event["registration_fee"], 2) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $event["registered_count"] ?> / <?= (int) $event["capacity"] ?></td>
                            <td>
                                <span class="status status-<?= htmlspecialchars($event["status"], ENT_QUOTES, "UTF-8") ?>">
                                    <?= htmlspecialchars(ucfirst($event["status"]), ENT_QUOTES, "UTF-8") ?>
                                </span>
                            </td>
                            <td>
                                <a href="/eventmanagements/event.php?id=<?= (int) $event["event_id"] ?>" class="btn btn-small">View</a>
                                <a href="/eventmanagements/organizer/edit-event.php?id=<?= (int) $event["event_id"] ?>" class="btn btn-small">Edit</a>
                                <a href="/eventmanagements/organizer/participants.php?id=<?= (int) $event["event_id"] ?>" class="btn btn-small">Participants</a>
                                <a href="/eventmanagements/organizer/attendance.php?id=<?= (int) $event["event_id"] ?>" class="btn btn-small">Attendance</a>
                                <a href="/eventmanagements/organizer/manage-prizes.php?id=<?= (int) $event["event_id"] ?>" class="btn btn-small">Prizes</a>
                                <a
                                    href="/eventmanagements/organizer/delete-event.php?id=<?= (int) $event["event_id"] ?>"
                                    class="btn btn-small btn-danger"
                                    data-confirm="Are you sure you want to delete this event?"
                                >
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</section>

<?php
$events_stmt->close();
require_once __DIR__ . "/../includes/footer.php";
?>