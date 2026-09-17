<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("organizer");

$organizer_id = (int) $_SESSION["user_id"];

$event_id = (int) (
    $_GET["id"] ??
    $_GET["event_id"] ??
    $_POST["event_id"] ??
    0
);

if ($event_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Check Event Ownership
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        event_id,
        title,
        event_date,
        event_time,
        venue
    FROM events
    WHERE event_id = ?
      AND organizer_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $event_id,
    $organizer_id
);

$stmt->execute();

$result = $stmt->get_result();
$event = $result->fetch_assoc();

$stmt->close();

if (!$event) {
    header("Location: dashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Mark Attendance
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $registration_id = (int) (
        $_POST["registration_id"] ?? 0
    );

    $attended = isset($_POST["attended"]) ? 1 : 0;

    if ($registration_id <= 0) {

        $error = "Invalid registration.";

    } else {

        /*
        |----------------------------------------------------------------------
        | Verify Registration Belongs to This Event
        |----------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT registration_id
            FROM registrations
            WHERE registration_id = ?
              AND event_id = ?
              AND status = 'registered'
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "ii",
                $registration_id,
                $event_id
            );

            $stmt->execute();

            $result = $stmt->get_result();
            $registration = $result->fetch_assoc();

            $stmt->close();

            if (!$registration) {

                $error = "Invalid participant.";

            } else {

                /*
                |------------------------------------------------------------------
                | Insert or Update Attendance
                |------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    INSERT INTO attendance
                        (registration_id, attended, marked_at)
                    VALUES
                        (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        attended = VALUES(attended),
                        marked_at = NOW()
                ");

                if (!$stmt) {

                    $error = "Database error: " . $conn->error;

                } else {

                    $stmt->bind_param(
                        "ii",
                        $registration_id,
                        $attended
                    );

                    if ($stmt->execute()) {

                        $message = "Attendance updated successfully.";

                    } else {

                        $error = "Failed to update attendance: " .
                                 $stmt->error;

                    }

                    $stmt->close();
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load Participants
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        r.registration_id,
        u.name,
        u.email,
        COALESCE(a.attended, 0) AS attended,
        a.marked_at
    FROM registrations r
    INNER JOIN users u
        ON u.user_id = r.user_id
    LEFT JOIN attendance a
        ON a.registration_id = r.registration_id
    WHERE r.event_id = ?
      AND r.status = 'registered'
    ORDER BY u.name ASC
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $event_id
);

$stmt->execute();

$participants = $stmt->get_result();

$stmt->close();

require_once __DIR__ . "/../includes/header.php";

?>

<div class="dashboard-header">

    <div>

        <h1>Attendance</h1>

        <p>
            <strong>
                <?php
                echo htmlspecialchars(
                    $event["title"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </strong>
        </p>

        <p>

            <?php
            echo htmlspecialchars(
                $event["event_date"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

            |

            <?php
            echo htmlspecialchars(
                $event["event_time"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

            |

            <?php
            echo htmlspecialchars(
                $event["venue"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

        </p>

    </div>

</div>

<?php if ($message): ?>

    <div class="alert success">

        <?php
        echo htmlspecialchars(
            $message,
            ENT_QUOTES,
            "UTF-8"
        );
        ?>

    </div>

<?php endif; ?>

<?php if ($error): ?>

    <div class="alert error">

        <?php
        echo htmlspecialchars(
            $error,
            ENT_QUOTES,
            "UTF-8"
        );
        ?>

    </div>

<?php endif; ?>

<p>

    <a
        href="/eventmanagements/organizer/participants.php?id=<?php echo $event_id; ?>"
        class="btn"
    >
        ← Back to Participants
    </a>

</p>

<?php if ($participants->num_rows === 0): ?>

    <div class="empty-state">

        <p>No registered participants yet.</p>

    </div>

<?php else: ?>

    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Attendance</th>
                    <th>Last Marked</th>
                    <th>Update</th>
                </tr>

            </thead>

            <tbody>

                <?php while ($participant = $participants->fetch_assoc()): ?>

                    <tr>

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

                            <?php if ((int) $participant["attended"] === 1): ?>

                                <span class="status status-approved">
                                    Present
                                </span>

                            <?php else: ?>

                                <span class="status status-pending">
                                    Absent / Not Marked
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if ($participant["marked_at"]): ?>

                                <?php
                                echo htmlspecialchars(
                                    $participant["marked_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            <?php else: ?>

                                Not marked

                            <?php endif; ?>

                        </td>

                        <td>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="event_id"
                                    value="<?php echo $event_id; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="registration_id"
                                    value="<?php echo (int) $participant["registration_id"]; ?>"
                                >

                                <label>

                                    <input
                                        type="checkbox"
                                        name="attended"
                                        value="1"
                                        <?php
                                        echo (int) $participant["attended"] === 1
                                            ? "checked"
                                            : "";
                                        ?>
                                    >

                                    Present

                                </label>

                                <button
                                    type="submit"
                                    class="btn btn-small"
                                >
                                    Save
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

<?php endif; ?>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>