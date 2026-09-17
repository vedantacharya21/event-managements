<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

requireRole("student");

$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Get All Student Registrations
|--------------------------------------------------------------------------
*/

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
        e.status AS event_status,

        r.registration_id,
        r.status AS registration_status,
        r.registered_at

    FROM registrations r

    INNER JOIN events e
        ON r.event_id = e.event_id

    WHERE r.user_id = ?

    ORDER BY
        e.event_date DESC,
        e.event_time DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("Unable to load registrations: " . $stmt->error);
}

$events = $stmt->get_result();

$stmt->close();

require_once __DIR__ . "/../includes/header.php";

?>

<section>

    <div class="dashboard-header">

        <div>

            <h1>My Events</h1>

            <p>
                View your registered events and registration status.
            </p>

        </div>

    </div>

    <p>

        <a
            href="/eventmanagements/student/dashboard.php"
            class="btn"
        >
            Dashboard
        </a>

        <a
            href="/eventmanagements/index.php"
            class="btn"
        >
            Browse Events
        </a>

    </p>

    <br>

    <?php if ($events->num_rows > 0): ?>

        <div class="event-grid">

            <?php while ($event = $events->fetch_assoc()): ?>

                <?php
                $event_status = (string) $event["event_status"];
                $registration_status =
                    (string) $event["registration_status"];

                $is_registered =
                    $registration_status === "registered";

                $is_approved =
                    $event_status === "approved";

                $is_completed =
                    $event_status === "completed";

                $event_status_class =
                    preg_replace(
                        "/[^a-zA-Z0-9_-]/",
                        "",
                        strtolower($event_status)
                    );

                $registration_status_class =
                    preg_replace(
                        "/[^a-zA-Z0-9_-]/",
                        "",
                        strtolower($registration_status)
                    );
                ?>

                <div class="event-card">

                    <?php if (!empty($event["poster"])): ?>

                        <img
                            src="/eventmanagements/uploads/posters/<?php
                            echo rawurlencode(
                                basename($event["poster"])
                            );
                            ?>"
                            alt="Event Poster"
                            class="event-poster"
                        >

                    <?php endif; ?>

                    <h3>
                        <?php
                        echo htmlspecialchars(
                            $event["title"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </h3>

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
                        <strong>Event Type:</strong>
                        <?php
                        echo htmlspecialchars(
                            ucfirst(
                                (string) $event["event_type"]
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Registration Fee:</strong>

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

                    </p>

                    <p>
                        <strong>Event Status:</strong>

                        <span class="status status-<?php
                        echo htmlspecialchars(
                            $event_status_class,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>">

                            <?php
                            echo htmlspecialchars(
                                ucfirst($event_status),
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </span>
                    </p>

                    <p>
                        <strong>Registration Status:</strong>

                        <span class="status status-<?php
                        echo htmlspecialchars(
                            $registration_status_class,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>">

                            <?php
                            echo htmlspecialchars(
                                ucfirst(
                                    $registration_status
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </span>
                    </p>

                    <p>
                        <strong>Registered At:</strong>

                        <?php
                        echo htmlspecialchars(
                            (string) $event["registered_at"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </p>

                    <p>

                        <a
                            href="/eventmanagements/event.php?id=<?php echo (int) $event["event_id"]; ?>"
                            class="btn btn-small"
                        >
                            View Event
                        </a>

                    </p>

                    <?php if ($is_registered && $is_approved): ?>

                        <p>

                            <a
                                href="/eventmanagements/student/cancel-registration.php?id=<?php echo (int) $event["event_id"]; ?>"
                                class="btn btn-small btn-danger"
                                data-confirm="Are you sure you want to cancel your registration?"
                            >
                                Cancel Registration
                            </a>

                        </p>

                    <?php endif; ?>

                    <?php if ($is_registered && ($is_approved || $is_completed)): ?>

                        <p>

                            <a
                                href="/eventmanagements/student/feedback.php"
                                class="btn btn-small btn-primary"
                            >
                                Feedback
                            </a>

                        </p>

                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">

            <p>
                You have not registered for any events yet.
            </p>

            <a
                href="/eventmanagements/index.php"
                class="btn"
            >
                Browse Events
            </a>

        </div>

    <?php endif; ?>

</section>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>