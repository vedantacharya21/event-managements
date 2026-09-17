<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("student");

$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Get Total Registered Events
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM registrations
    WHERE user_id = ?
      AND status = 'registered'
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("Failed to load registered events: " . $stmt->error);
}

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$total_events = (int) ($row["total"] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Get Completed Events Attended
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM registrations r
    INNER JOIN attendance a
        ON r.registration_id = a.registration_id
    INNER JOIN events e
        ON r.event_id = e.event_id
    WHERE r.user_id = ?
      AND r.status = 'registered'
      AND a.attended = 1
      AND e.status = 'completed'
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("Failed to load attended events: " . $stmt->error);
}

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$attended_events = (int) ($row["total"] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Get Feedback Count
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM feedback
    WHERE user_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("Failed to load feedback count: " . $stmt->error);
}

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$feedback_count = (int) ($row["total"] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Get Upcoming Registered Events
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        e.event_id,
        e.title,
        e.event_date,
        e.event_time,
        e.venue,
        e.registration_fee,
        e.event_type
    FROM registrations r
    INNER JOIN events e
        ON r.event_id = e.event_id
    WHERE r.user_id = ?
      AND r.status = 'registered'
      AND e.status = 'approved'
      AND e.event_date >= CURDATE()
    ORDER BY
        e.event_date ASC,
        e.event_time ASC
    LIMIT 5
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("Failed to load upcoming events: " . $stmt->error);
}

$upcoming_events = $stmt->get_result();

$stmt->close();

require_once __DIR__ . "/../includes/header.php";

?>

<section>

    <div class="dashboard-header">

        <div>

            <h1>Student Dashboard</h1>

            <p>
                Welcome,

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION["name"] ?? "Student",
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </strong>
            </p>

        </div>

    </div>

    <!-- Dashboard Statistics -->

    <section>

        <h2>My Statistics</h2>

        <div class="stats-grid">

            <div class="stat-card">

                <h3>Registered Events</h3>

                <p>
                    <?php echo $total_events; ?>
                </p>

            </div>

            <div class="stat-card">

                <h3>Attended Events</h3>

                <p>
                    <?php echo $attended_events; ?>
                </p>

            </div>

            <div class="stat-card">

                <h3>Feedback Given</h3>

                <p>
                    <?php echo $feedback_count; ?>
                </p>

            </div>

        </div>

    </section>

    <br>

    <!-- Student Navigation -->

    <section>

        <h2>Student Menu</h2>

        <p>

            <a
                href="/eventmanagements/index.php"
                class="btn"
            >
                Browse Events
            </a>

            <a
                href="/eventmanagements/student/my-events.php"
                class="btn"
            >
                My Events
            </a>

        </p>

    </section>

    <br>

    <!-- Upcoming Events -->

    <section>

        <h2>My Upcoming Events</h2>

        <?php if ($upcoming_events->num_rows > 0): ?>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Type</th>
                            <th>Fee</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php while ($event = $upcoming_events->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $event["title"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $event["event_date"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $event["event_time"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $event["venue"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst($event["event_type"]),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php if ((float) $event["registration_fee"] > 0): ?>

                                        ₹<?php
                                        echo number_format(
                                            (float) $event["registration_fee"],
                                            2
                                        );
                                        ?>

                                    <?php else: ?>

                                        Free

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <a
                                        href="/eventmanagements/event.php?id=<?php echo (int) $event["event_id"]; ?>"
                                        class="btn btn-small"
                                    >
                                        View
                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <p>
                    You have not registered for any upcoming events.
                </p>

                <a
                    href="/eventmanagements/index.php"
                    class="btn"
                >
                    Browse Available Events
                </a>

            </div>

        <?php endif; ?>

    </section>

</section>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>