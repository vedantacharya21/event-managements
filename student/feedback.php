<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

requireRole("student");

$user_id = (int) $_SESSION["user_id"];

$success = "";
$error = "";

$event_id = 0;
$rating = 0;
$comments = "";

/*
|--------------------------------------------------------------------------
| Display Success Messages
|--------------------------------------------------------------------------
*/

if (isset($_GET["success"])) {
    switch ($_GET["success"]) {
        case "feedback_added":
            $success = "Feedback submitted successfully.";
            break;

        case "feedback_updated":
            $success = "Feedback updated successfully.";
            break;

        case "feedback_deleted":
            $success = "Feedback deleted successfully.";
            break;
    }
}

/*
|--------------------------------------------------------------------------
| Submit Feedback
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = (int) ($_POST["event_id"] ?? 0);
    $rating = (int) ($_POST["rating"] ?? 0);
    $comments = trim($_POST["comments"] ?? "");

    if ($event_id <= 0) {

        $error = "Please select a valid event.";

    } elseif ($rating < 1 || $rating > 5) {

        $error = "Please select a rating between 1 and 5.";

    } elseif ($comments === "") {

        $error = "Please enter your feedback.";

    } elseif (strlen($comments) > 1000) {

        $error = "Feedback cannot exceed 1000 characters.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Registration
        |--------------------------------------------------------------------------
        */

        $check_stmt = $conn->prepare("
            SELECT registration_id
            FROM registrations
            WHERE event_id = ?
              AND user_id = ?
              AND status = 'registered'
            LIMIT 1
        ");

        if (!$check_stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $check_stmt->bind_param(
                "ii",
                $event_id,
                $user_id
            );

            if (!$check_stmt->execute()) {

                $error = "Unable to check registration.";

            } else {

                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows === 0) {
                    $error = "You are not registered for this event.";
                }
            }

            $check_stmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Existing Feedback
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $duplicate_stmt = $conn->prepare("
            SELECT feedback_id
            FROM feedback
            WHERE event_id = ?
              AND user_id = ?
            LIMIT 1
        ");

        if (!$duplicate_stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $duplicate_stmt->bind_param(
                "ii",
                $event_id,
                $user_id
            );

            if (!$duplicate_stmt->execute()) {

                $error = "Unable to check existing feedback.";

            } else {

                $duplicate_result = $duplicate_stmt->get_result();

                if ($duplicate_result->num_rows > 0) {
                    $error = "You have already submitted feedback for this event.";
                }
            }

            $duplicate_stmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Insert Feedback
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $insert_stmt = $conn->prepare("
            INSERT INTO feedback (
                event_id,
                user_id,
                rating,
                comments
            )
            VALUES (?, ?, ?, ?)
        ");

        if (!$insert_stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $insert_stmt->bind_param(
                "iiis",
                $event_id,
                $user_id,
                $rating,
                $comments
            );

            if ($insert_stmt->execute()) {

                $insert_stmt->close();

                header(
                    "Location: /eventmanagements/student/feedback.php?success=feedback_added"
                );

                exit();

            } else {

                $error = "Unable to submit feedback: " .
                    $insert_stmt->error;
            }

            $insert_stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get Registered Events
|--------------------------------------------------------------------------
*/

$events = [];

$events_stmt = $conn->prepare("
    SELECT DISTINCT
        e.event_id,
        e.title,
        e.event_date
    FROM events e
    INNER JOIN registrations r
        ON r.event_id = e.event_id
    WHERE r.user_id = ?
      AND r.status = 'registered'
      AND e.status IN ('approved', 'completed')
    ORDER BY e.event_date DESC
");

if (!$events_stmt) {

    $error = "Unable to load events: " . $conn->error;

} else {

    $events_stmt->bind_param(
        "i",
        $user_id
    );

    if ($events_stmt->execute()) {

        $events_result = $events_stmt->get_result();

        while ($row = $events_result->fetch_assoc()) {
            $events[] = $row;
        }

    } else {

        $error = "Unable to load registered events.";
    }

    $events_stmt->close();
}

/*
|--------------------------------------------------------------------------
| Get Student Feedback
|--------------------------------------------------------------------------
*/

$feedback_list = [];

$feedback_stmt = $conn->prepare("
    SELECT
        f.feedback_id,
        f.event_id,
        f.rating,
        f.comments,
        f.created_at,
        e.title,
        e.event_date
    FROM feedback f
    INNER JOIN events e
        ON e.event_id = f.event_id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");

if (!$feedback_stmt) {

    $error = "Unable to load feedback: " . $conn->error;

} else {

    $feedback_stmt->bind_param(
        "i",
        $user_id
    );

    if ($feedback_stmt->execute()) {

        $feedback_result = $feedback_stmt->get_result();

        while ($row = $feedback_result->fetch_assoc()) {
            $feedback_list[] = $row;
        }

    } else {

        $error = "Unable to load feedback.";
    }

    $feedback_stmt->close();
}

require_once __DIR__ . "/../includes/header.php";

?>

<div class="page-header">

    <h1>Event Feedback</h1>

    <p>Share your experience about college events.</p>

</div>

<?php if ($success !== ""): ?>

    <div class="alert alert-success">
        <?php
        echo htmlspecialchars(
            $success,
            ENT_QUOTES,
            "UTF-8"
        );
        ?>
    </div>

<?php endif; ?>

<?php if ($error !== ""): ?>

    <div class="alert alert-danger">
        <?php
        echo htmlspecialchars(
            $error,
            ENT_QUOTES,
            "UTF-8"
        );
        ?>
    </div>

<?php endif; ?>

<!-- Submit Feedback -->

<section class="card">

    <h2>Submit Feedback</h2>

    <?php if (count($events) > 0): ?>

        <form
            method="POST"
            action="/eventmanagements/student/feedback.php"
            class="form"
        >

            <div class="form-group">

                <label for="event_id">
                    Select Event
                </label>

                <select
                    name="event_id"
                    id="event_id"
                    required
                >

                    <option value="">
                        -- Select Event --
                    </option>

                    <?php foreach ($events as $event): ?>

                        <option
                            value="<?php echo (int) $event["event_id"]; ?>"
                            <?php
                            echo (
                                $event_id ===
                                (int) $event["event_id"]
                            ) ? "selected" : "";
                            ?>
                        >

                            <?php
                            echo htmlspecialchars(
                                $event["title"],
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                            -

                            <?php
                            echo htmlspecialchars(
                                $event["event_date"],
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label for="rating">
                    Rating
                </label>

                <select
                    name="rating"
                    id="rating"
                    required
                >

                    <option value="">
                        -- Select Rating --
                    </option>

                    <option
                        value="5"
                        <?php echo $rating === 5 ? "selected" : ""; ?>
                    >
                        5 - Excellent
                    </option>

                    <option
                        value="4"
                        <?php echo $rating === 4 ? "selected" : ""; ?>
                    >
                        4 - Very Good
                    </option>

                    <option
                        value="3"
                        <?php echo $rating === 3 ? "selected" : ""; ?>
                    >
                        3 - Good
                    </option>

                    <option
                        value="2"
                        <?php echo $rating === 2 ? "selected" : ""; ?>
                    >
                        2 - Average
                    </option>

                    <option
                        value="1"
                        <?php echo $rating === 1 ? "selected" : ""; ?>
                    >
                        1 - Poor
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="comments">
                    Your Feedback
                </label>

                <textarea
                    name="comments"
                    id="comments"
                    rows="5"
                    maxlength="1000"
                    placeholder="Write your feedback here..."
                    required
                ><?php
                echo htmlspecialchars(
                    $comments,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?></textarea>

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Submit Feedback
            </button>

        </form>

    <?php else: ?>

        <p>
            You do not have any registered events available for feedback.
        </p>

    <?php endif; ?>

</section>

<!-- Feedback History -->

<section class="card">

    <h2>My Feedback</h2>

    <?php if (count($feedback_list) > 0): ?>

        <div class="table-responsive">

            <table class="data-table">

                <thead>

                    <tr>
                        <th>Event</th>
                        <th>Rating</th>
                        <th>Comments</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($feedback_list as $feedback): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $feedback["title"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int) $feedback["rating"];
                                ?>/5
                            </td>

                            <td>
                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $feedback["comments"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $feedback["created_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>
                            </td>

                            <td>

                                <a
                                    href="/eventmanagements/student/edit-feedback.php?id=<?php echo (int) $feedback["feedback_id"]; ?>"
                                    class="btn btn-small btn-secondary"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    action="/eventmanagements/student/delete-feedback.php"
                                    style="display: inline;"
                                    onsubmit="return confirm('Are you sure you want to delete this feedback?');"
                                >

                                    <input
                                        type="hidden"
                                        name="feedback_id"
                                        value="<?php echo (int) $feedback["feedback_id"]; ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-small btn-danger"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <p>
            You have not submitted any feedback yet.
        </p>

    <?php endif; ?>

</section>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>