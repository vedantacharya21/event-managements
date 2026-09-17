<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("organizer");

$organizer_id = (int) $_SESSION["user_id"];

$event_id = (int) (
    $_GET["id"]
    ?? $_POST["event_id"]
    ?? 0
);

if ($event_id <= 0) {
    header("Location: /eventmanagements/organizer/dashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Verify Event Belongs To Organizer
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT event_id, title, poster
    FROM events
    WHERE event_id = ?
      AND organizer_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Event query failed: " . $conn->error);
}

$stmt->bind_param("ii", $event_id, $organizer_id);
$stmt->execute();

$event = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$event) {
    die("Event not found or you do not have permission to delete it.");
}

/*
|--------------------------------------------------------------------------
| Delete Event
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $conn->begin_transaction();

    try {

        /*
        | Delete related records manually.
        | This works even when foreign keys do not use ON DELETE CASCADE.
        */

        $delete_feedback = $conn->prepare("
            DELETE FROM feedback
            WHERE event_id = ?
        ");

        if (!$delete_feedback) {
            throw new Exception(
                "Feedback delete query failed: " . $conn->error
            );
        }

        $delete_feedback->bind_param("i", $event_id);
        $delete_feedback->execute();
        $delete_feedback->close();

        // Winners reference prizes (prize_id), not events directly.
        // Delete winners whose prize belongs to this event.
        $delete_winners = $conn->prepare("
            DELETE FROM winners
            WHERE prize_id IN (
                SELECT prize_id
                FROM prizes
                WHERE event_id = ?
            )
        ");

        if (!$delete_winners) {
            throw new Exception(
                "Winners delete query failed: " . $conn->error
            );
        }

        $delete_winners->bind_param("i", $event_id);
        $delete_winners->execute();
        $delete_winners->close();

        $delete_prizes = $conn->prepare("
            DELETE FROM prizes
            WHERE event_id = ?
        ");

        if (!$delete_prizes) {
            throw new Exception(
                "Prizes delete query failed: " . $conn->error
            );
        }

        $delete_prizes->bind_param("i", $event_id);
        $delete_prizes->execute();
        $delete_prizes->close();

        $delete_attendance = $conn->prepare("
            DELETE FROM attendance
            WHERE registration_id IN (
                SELECT registration_id
                FROM registrations
                WHERE event_id = ?
            )
        ");

        if (!$delete_attendance) {
            throw new Exception(
                "Attendance delete query failed: " . $conn->error
            );
        }

        $delete_attendance->bind_param("i", $event_id);
        $delete_attendance->execute();
        $delete_attendance->close();

        $delete_registrations = $conn->prepare("
            DELETE FROM registrations
            WHERE event_id = ?
        ");

        if (!$delete_registrations) {
            throw new Exception(
                "Registrations delete query failed: " . $conn->error
            );
        }

        $delete_registrations->bind_param("i", $event_id);
        $delete_registrations->execute();
        $delete_registrations->close();

        $delete_categories = $conn->prepare("
            DELETE FROM event_categories
            WHERE event_id = ?
        ");

        if (!$delete_categories) {
            throw new Exception(
                "Event categories delete query failed: " . $conn->error
            );
        }

        $delete_categories->bind_param("i", $event_id);
        $delete_categories->execute();
        $delete_categories->close();

        /*
        |--------------------------------------------------------------------------
        | Delete Main Event
        |--------------------------------------------------------------------------
        */

        $delete_event = $conn->prepare("
            DELETE FROM events
            WHERE event_id = ?
              AND organizer_id = ?
        ");

        if (!$delete_event) {
            throw new Exception(
                "Event delete query failed: " . $conn->error
            );
        }

        $delete_event->bind_param(
            "ii",
            $event_id,
            $organizer_id
        );

        $delete_event->execute();

        if ($delete_event->affected_rows !== 1) {
            throw new Exception("Event could not be deleted.");
        }

        $delete_event->close();

        $conn->commit();

        /*
        |--------------------------------------------------------------------------
        | Delete Poster File
        |--------------------------------------------------------------------------
        */

        if (!empty($event["poster"])) {

            $poster_file = __DIR__ .
                "/../uploads/posters/" .
                basename($event["poster"]);

            if (file_exists($poster_file)) {
                unlink($poster_file);
            }
        }

        header(
            "Location: /eventmanagements/organizer/dashboard.php"
        );

        exit();

    } catch (Exception $e) {

        $conn->rollback();

        die(
            "Delete failed: " .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                "UTF-8"
            )
        );
    }
}

require_once __DIR__ . "/../includes/header.php";

?>

<section class="form-container">

    <h1>Delete Event</h1>

    <div class="alert error">

        <p>
            Are you sure you want to delete this event?
        </p>

        <h2>
            <?php
            echo htmlspecialchars(
                $event["title"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </h2>

        <p>
            This action will also remove registrations,
            attendance records, prizes, winners and feedback
            related to this event.
        </p>

        <p>
            <strong>
                This action cannot be undone.
            </strong>
        </p>

    </div>

    <form method="POST">

        <input
            type="hidden"
            name="event_id"
            value="<?php echo $event_id; ?>"
        >

        <button
            type="submit"
            class="btn btn-danger"
        >
            Yes, Delete Event
        </button>

        <a
            href="/eventmanagements/organizer/dashboard.php"
            class="btn"
        >
            Cancel
        </a>

    </form>

</section>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>