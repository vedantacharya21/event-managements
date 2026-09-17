<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("admin");

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = (int) ($_POST["event_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($event_id <= 0) {
        $error = "Invalid event.";
    } elseif (in_array($action, ["approve", "reject", "complete"], true)) {

        $allowed_statuses = [
            "approve" => ["approved", "pending"],
            "reject" => ["rejected", "pending"],
            "complete" => ["completed", "approved"]
        ];

        [$new_status, $old_status] = $allowed_statuses[$action];

        $stmt = $conn->prepare("
            UPDATE events
            SET status = ?
            WHERE event_id = ?
            AND status = ?
        ");

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("sis", $new_status, $event_id, $old_status);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message = "Event status updated successfully.";
                } else {
                    $error = "Event status could not be updated.";
                }
            } else {
                $error = "Failed to update event status.";
            }

            $stmt->close();
        }

    } elseif ($action === "delete") {

        $stmt = $conn->prepare("SELECT poster FROM events WHERE event_id = ? LIMIT 1");

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {

            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $event_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$event_data) {
                $error = "Event not found.";
            } else {

                $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");

                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {

                    $stmt->bind_param("i", $event_id);

                    if ($stmt->execute()) {
                        if (!empty($event_data["poster"])) {
                            $poster_path = __DIR__ . "/../" . ltrim($event_data["poster"], "/\\");
                            if (is_file($poster_path)) {
                                unlink($poster_path);
                            }
                        }
                        $message = "Event deleted successfully.";
                    } else {
                        $error = "Failed to delete event.";
                    }

                    $stmt->close();
                }
            }
        }

    } else {
        $error = "Invalid action.";
    }
}

$events = $conn->query("
    SELECT
        e.event_id,
        e.title,
        e.event_date,
        e.event_time,
        e.venue,
        e.capacity,
        e.registration_fee,
        e.event_type,
        e.status,
        u.name AS organizer_name,
        (
            SELECT COUNT(*)
            FROM registrations r
            WHERE r.event_id = e.event_id
            AND r.status = 'registered'
        ) AS registered_count
    FROM events e
    INNER JOIN users u ON u.user_id = e.organizer_id
    ORDER BY e.created_at DESC
");

if (!$events) {
    die("Events query failed: " . $conn->error);
}

require_once __DIR__ . "/../includes/header.php";
?>

<h1>Manage Events</h1>

<?php if ($message !== ""): ?>
    <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<?php if ($error !== ""): ?>
    <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<?php if ($events->num_rows === 0): ?>

    <div class="empty-state"><p>No events found.</p></div>

<?php else: ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Organizer</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Registered</th>
                    <th>Fee</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($event = $events->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($event["organizer_name"], ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <?= htmlspecialchars($event["event_date"], ENT_QUOTES, "UTF-8") ?><br>
                            <?= htmlspecialchars($event["event_time"], ENT_QUOTES, "UTF-8") ?>
                        </td>
                        <td><?= htmlspecialchars(ucfirst($event["event_type"]), ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= (int) $event["capacity"] ?></td>
                        <td><?= (int) $event["registered_count"] ?></td>
                        <td>
                            <?php if ((float) $event["registration_fee"] == 0): ?>
                                Free
                            <?php else: ?>
                                ₹<?= number_format((float) $event["registration_fee"], 2) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status status-<?= htmlspecialchars($event["status"], ENT_QUOTES, "UTF-8") ?>">
                                <?= htmlspecialchars(ucfirst($event["status"]), ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($event["status"] === "pending"): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="event_id" value="<?= (int) $event["event_id"] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-small">Approve</button>
                                </form>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="event_id" value="<?= (int) $event["event_id"] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-small">Reject</button>
                                </form>
                            <?php elseif ($event["status"] === "approved"): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="event_id" value="<?= (int) $event["event_id"] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-small">Mark Completed</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" class="inline-form">
                                <input type="hidden" name="event_id" value="<?= (int) $event["event_id"] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button
                                    type="submit"
                                    class="btn btn-danger btn-small"
                                    data-confirm="Delete this event permanently?"
                                >
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<br>

<a href="/eventmanagements/admin/dashboard.php" class="btn">← Back to Dashboard</a>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>