<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("organizer");

$organizer_id = (int) $_SESSION["user_id"];

$event_id = (int) ($_GET["id"] ?? $_GET["event_id"] ?? 0);

if ($event_id <= 0) {
    header("Location: /eventmanagements/organizer/dashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Get Event
|--------------------------------------------------------------------------
*/

$event_stmt = $conn->prepare("
    SELECT
        event_id,
        title,
        event_date,
        event_time,
        venue,
        capacity,
        registration_fee,
        status
    FROM events
    WHERE event_id = ?
      AND organizer_id = ?
    LIMIT 1
");

if (!$event_stmt) {
    die("Database error: " . $conn->error);
}

$event_stmt->bind_param(
    "ii",
    $event_id,
    $organizer_id
);

if (!$event_stmt->execute()) {
    die("Failed to load event: " . $event_stmt->error);
}

$event_result = $event_stmt->get_result();
$event = $event_result->fetch_assoc();

$event_stmt->close();

if (!$event) {
    header("Location: /eventmanagements/organizer/dashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Get Participants
|--------------------------------------------------------------------------
*/

$participant_stmt = $conn->prepare("
    SELECT
        r.registration_id,
        r.registered_at,
        u.user_id,
        u.name,
        u.email,
        COALESCE(a.attended, 0) AS attended,
        a.marked_at
    FROM registrations r
    INNER JOIN users u
        ON r.user_id = u.user_id
    LEFT JOIN attendance a
        ON r.registration_id = a.registration_id
    WHERE r.event_id = ?
      AND r.status = 'registered'
    ORDER BY r.registered_at ASC
");

if (!$participant_stmt) {
    die("Database error: " . $conn->error);
}

$participant_stmt->bind_param(
    "i",
    $event_id
);

if (!$participant_stmt->execute()) {
    die("Failed to load participants: " . $participant_stmt->error);
}

$participants = $participant_stmt->get_result();

$registered_count = $participants->num_rows;

$participant_stmt->close();

require_once __DIR__ . "/../includes/header.php";

?>

<section>

    <div class="dashboard-header">

        <div>

            <h1>Participants</h1>

            <h2>
                <?php
                echo htmlspecialchars(
                    $event["title"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </h2>

        </div>

    </div>

    <div class="event-details">

        <p>
            <strong>Date:</strong>

            <?php
            echo htmlspecialchars(
                $event["event_date"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

        <p>
            <strong>Time:</strong>

            <?php
            echo htmlspecialchars(
                $event["event_time"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

        <p>
            <strong>Venue:</strong>

            <?php
            echo htmlspecialchars(
                $event["venue"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

        <p>
            <strong>Capacity:</strong>

            <?php
            echo (int) $event["capacity"];
            ?>
        </p>

        <p>
            <strong>Registered:</strong>

            <?php echo $registered_count; ?>

            /

            <?php echo (int) $event["capacity"]; ?>

        </p>

        <p>
            <strong>Status:</strong>

            <span class="status status-<?php
                echo htmlspecialchars(
                    $event["status"],
                    ENT_QUOTES,
                    "UTF-8"
                );
            ?>">

                <?php
                echo htmlspecialchars(
                    ucfirst($event["status"]),
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </span>

        </p>

    </div>

    <p>

        <a
            href="/eventmanagements/organizer/attendance.php?id=<?php echo $event_id; ?>"
            class="btn"
        >
            Manage Attendance
        </a>

    </p>

    <?php if ($registered_count === 0): ?>

        <div class="empty-state">

            <p>
                No students have registered for this event yet.
            </p>

        </div>

    <?php else: ?>

        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Registered At</th>
                        <th>Attendance</th>
                    </tr>

                </thead>

                <tbody>

                    <?php $number = 1; ?>

                    <?php while ($participant = $participants->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo $number++; ?>
                            </td>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $participant["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $participant["email"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $participant["registered_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </td>

                            <td>

                                <?php if ((int) $participant["attended"] === 1): ?>

                                    <span class="status status-approved">
                                        Attended
                                    </span>

                                <?php else: ?>

                                    <span class="status status-pending">
                                        Not Marked
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

    <br>

    <a
        href="/eventmanagements/organizer/dashboard.php"
        class="btn"
    >
        ← Back to Dashboard
    </a>

</section>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>