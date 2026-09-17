<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

requireRole("student");

$user_id = (int) $_SESSION["user_id"];

$event_id = (int) (
    $_POST["event_id"]
    ?? $_GET["event_id"]
    ?? $_GET["id"]
    ?? 0
);

if ($event_id <= 0) {
    header("Location: /eventmanagements/index.php");
    exit();
}

$error = "";
$success = "";
$event = null;

/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function fetchEvent(mysqli $conn, int $event_id): ?array
{
    $stmt = $conn->prepare(
        "SELECT
            event_id,
            title,
            description,
            event_date,
            event_time,
            venue,
            capacity,
            registration_fee,
            event_type,
            status
         FROM events
         WHERE event_id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception(
            "Event query preparation failed: " . $conn->error
        );
    }

    $stmt->bind_param("i", $event_id);

    if (!$stmt->execute()) {
        throw new Exception(
            "Event query execution failed: " . $stmt->error
        );
    }

    $result = $stmt->get_result();
    $event = $result->fetch_assoc();

    $stmt->close();

    return $event ?: null;
}

/*
|--------------------------------------------------------------------------
| Registration Process
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        $conn->begin_transaction();

        /*
        |--------------------------------------------------------------------------
        | Lock Event Row
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT
                event_id,
                title,
                description,
                event_date,
                event_time,
                venue,
                capacity,
                registration_fee,
                event_type,
                status
             FROM events
             WHERE event_id = ?
             FOR UPDATE"
        );

        if (!$stmt) {
            throw new Exception(
                "Event query preparation failed: " . $conn->error
            );
        }

        $stmt->bind_param("i", $event_id);

        if (!$stmt->execute()) {
            throw new Exception(
                "Event query execution failed: " . $stmt->error
            );
        }

        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        $stmt->close();

        if (!$event) {
            throw new Exception("Event not found.");
        }

        /*
        |--------------------------------------------------------------------------
        | Check Event Status
        |--------------------------------------------------------------------------
        */

        if ($event["status"] !== "approved") {
            throw new Exception(
                "This event is not available for registration."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check Event Date
        |--------------------------------------------------------------------------
        */

        if ($event["event_date"] < date("Y-m-d")) {
            throw new Exception(
                "Registration is closed because this event has passed."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check Existing Registration
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT
                registration_id,
                status
             FROM registrations
             WHERE user_id = ?
               AND event_id = ?
             LIMIT 1
             FOR UPDATE"
        );

        if (!$stmt) {
            throw new Exception(
                "Registration query preparation failed: " . $conn->error
            );
        }

        $stmt->bind_param(
            "ii",
            $user_id,
            $event_id
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "Registration query execution failed: " . $stmt->error
            );
        }

        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();

        $stmt->close();

        if (
            $existing &&
            $existing["status"] === "registered"
        ) {
            throw new Exception(
                "You are already registered for this event."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Count Active Registrations
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM registrations
             WHERE event_id = ?
               AND status = 'registered'"
        );

        if (!$stmt) {
            throw new Exception(
                "Capacity query preparation failed: " . $conn->error
            );
        }

        $stmt->bind_param("i", $event_id);

        if (!$stmt->execute()) {
            throw new Exception(
                "Capacity query execution failed: " . $stmt->error
            );
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $registered_count = (int) ($row["total"] ?? 0);

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | Check Capacity
        |--------------------------------------------------------------------------
        */

        if ($registered_count >= (int) $event["capacity"]) {
            throw new Exception(
                "Sorry, this event is full."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Reactivate or Create Registration
        |--------------------------------------------------------------------------
        */

        if ($existing) {

            $registration_id = (int) $existing["registration_id"];

            $stmt = $conn->prepare(
                "UPDATE registrations
                 SET
                    status = 'registered',
                    registered_at = CURRENT_TIMESTAMP
                 WHERE registration_id = ?
                   AND user_id = ?
                   AND event_id = ?"
            );

            if (!$stmt) {
                throw new Exception(
                    "Update query preparation failed: " . $conn->error
                );
            }

            $stmt->bind_param(
                "iii",
                $registration_id,
                $user_id,
                $event_id
            );

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO registrations
                    (user_id, event_id, status, registered_at)
                 VALUES
                    (?, ?, 'registered', CURRENT_TIMESTAMP)"
            );

            if (!$stmt) {
                throw new Exception(
                    "Insert query preparation failed: " . $conn->error
                );
            }

            $stmt->bind_param(
                "ii",
                $user_id,
                $event_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Execute Registration
        |--------------------------------------------------------------------------
        */

        if (!$stmt->execute()) {
            throw new Exception(
                "Registration failed: " . $stmt->error
            );
        }

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | Commit Transaction
        |--------------------------------------------------------------------------
        */

        $conn->commit();

        $success = "Registration successful for " . $event["title"] . ".";

    } catch (Exception $e) {

        $conn->rollback();

        $error = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Registration Success Page
|--------------------------------------------------------------------------
*/

if ($success !== "") {

    require_once __DIR__ . "/../includes/header.php";

    ?>

    <div class="auth-container">
        <div class="auth-card">

            <div class="alert success">
                <?php
                echo htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>

            <h2>Registration Confirmed</h2>

            <p>
                Your registration has been recorded successfully.
            </p>

            <?php if (
                isset($event["registration_fee"]) &&
                (float) $event["registration_fee"] > 0
            ): ?>

                <p class="event-fee">
                    <strong>Registration Fee:</strong>
                    ₹<?php echo number_format(
                        (float) $event["registration_fee"],
                        2
                    ); ?>
                </p>

                <p>
                    Payment processing is not included in this project.
                </p>

            <?php endif; ?>

            <br>

            <a
                href="/eventmanagements/student/my-events.php"
                class="btn btn-primary btn-full"
            >
                View My Events
            </a>

            <br><br>

            <a
                href="/eventmanagements/index.php"
                class="btn btn-secondary btn-full"
            >
                Browse More Events
            </a>

        </div>
    </div>

    <?php

    require_once __DIR__ . "/../includes/footer.php";

    exit();
}

/*
|--------------------------------------------------------------------------
| Load Event for Confirmation Page
|--------------------------------------------------------------------------
*/

try {

    $event = fetchEvent($conn, $event_id);

} catch (Exception $e) {

    die(
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

if (!$event) {
    die("Event not found.");
}

/*
|--------------------------------------------------------------------------
| Check Event Status
|--------------------------------------------------------------------------
*/

if ($event["status"] !== "approved") {

    require_once __DIR__ . "/../includes/header.php";

    ?>

    <div class="alert error">
        This event is not available for registration.
    </div>

    <a
        href="/eventmanagements/index.php"
        class="btn btn-secondary"
    >
        Browse Events
    </a>

    <?php

    require_once __DIR__ . "/../includes/footer.php";

    exit();
}

/*
|--------------------------------------------------------------------------
| Check Existing Registration
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT registration_id
     FROM registrations
     WHERE user_id = ?
       AND event_id = ?
       AND status = 'registered'
     LIMIT 1"
);

if (!$stmt) {
    die(
        "Registration query preparation failed: "
        . htmlspecialchars($conn->error, ENT_QUOTES, "UTF-8")
    );
}

$stmt->bind_param(
    "ii",
    $user_id,
    $event_id
);

if (!$stmt->execute()) {
    die(
        "Registration query execution failed: "
        . htmlspecialchars($stmt->error, ENT_QUOTES, "UTF-8")
    );
}

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $stmt->close();

    header(
        "Location: /eventmanagements/event.php?id=" . $event_id
    );

    exit();
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Count Available Seats
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM registrations
     WHERE event_id = ?
       AND status = 'registered'"
);

if (!$stmt) {
    die(
        "Capacity query preparation failed: "
        . htmlspecialchars($conn->error, ENT_QUOTES, "UTF-8")
    );
}

$stmt->bind_param("i", $event_id);

if (!$stmt->execute()) {
    die(
        "Capacity query execution failed: "
        . htmlspecialchars($stmt->error, ENT_QUOTES, "UTF-8")
    );
}

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$registered_count = (int) ($row["total"] ?? 0);

$stmt->close();

$available_seats =
    (int) $event["capacity"] - $registered_count;

require_once __DIR__ . "/../includes/header.php";

?>

<div class="auth-container">

    <div class="auth-card">

        <h1>Confirm Registration</h1>

        <?php if ($error !== ""): ?>

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

        <h2>
            <?php
            echo htmlspecialchars(
                $event["title"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </h2>

        <div class="event-info">

            <p>
                <strong>Date:</strong>
                <?php
                echo date(
                    "d M Y",
                    strtotime($event["event_date"])
                );
                ?>
            </p>

            <p>
                <strong>Time:</strong>
                <?php
                echo date(
                    "h:i A",
                    strtotime($event["event_time"])
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
                <strong>Available Seats:</strong>
                <?php echo max(0, $available_seats); ?>
            </p>

        </div>

        <div class="event-fee-large">

            <strong>Registration Fee:</strong>

            <?php if (
                (float) $event["registration_fee"] > 0
            ): ?>

                ₹<?php echo number_format(
                    (float) $event["registration_fee"],
                    2
                ); ?>

            <?php else: ?>

                Free

            <?php endif; ?>

        </div>

        <?php if ($available_seats <= 0): ?>

            <div class="alert error">
                This event is full.
            </div>

            <a
                href="/eventmanagements/event.php?id=<?php echo $event_id; ?>"
                class="btn btn-secondary btn-full"
            >
                Back to Event
            </a>

        <?php else: ?>

            <form
                method="POST"
                action="/eventmanagements/student/register-event.php"
            >

                <input
                    type="hidden"
                    name="event_id"
                    value="<?php echo $event_id; ?>"
                >

                <?php if (
                    (float) $event["registration_fee"] > 0
                ): ?>

                    <div class="alert">

                        Registration Fee:
                        ₹<?php echo number_format(
                            (float) $event["registration_fee"],
                            2
                        ); ?>

                        <br>

                        <small>
                            This project records the registration fee
                            but does not process online payment.
                        </small>

                    </div>

                <?php endif; ?>

                <button
                    type="submit"
                    class="btn btn-primary btn-full"
                >
                    Confirm Registration
                </button>

            </form>

            <br>

            <a
                href="/eventmanagements/event.php?id=<?php echo $event_id; ?>"
                class="btn btn-secondary btn-full"
            >
                Cancel
            </a>

        <?php endif; ?>

    </div>

</div>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>