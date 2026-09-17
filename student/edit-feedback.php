<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

requireRole("student");

$user_id = (int) $_SESSION["user_id"];
$feedback_id = (int) ($_GET["id"] ?? 0);

if ($feedback_id <= 0) {
    header("Location: /eventmanagements/student/feedback.php");
    exit();
}

$error = "";

/*
|--------------------------------------------------------------------------
| Fetch Feedback
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        f.feedback_id,
        f.rating,
        f.comments,
        e.title
    FROM feedback f
    INNER JOIN events e
        ON e.event_id = f.event_id
    WHERE f.feedback_id = ?
      AND f.user_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $feedback_id,
    $user_id
);

if (!$stmt->execute()) {
    die("Unable to fetch feedback: " . $stmt->error);
}

$result = $stmt->get_result();
$feedback = $result->fetch_assoc();

$stmt->close();

if (!$feedback) {
    header("Location: /eventmanagements/student/feedback.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Update Feedback
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $rating = (int) ($_POST["rating"] ?? 0);
    $comments = trim($_POST["comments"] ?? "");

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5.";
    } elseif ($comments === "") {
        $error = "Please enter your feedback.";
    } elseif (strlen($comments) > 1000) {
        $error = "Feedback must not exceed 1000 characters.";
    } else {

        $stmt = $conn->prepare("
            UPDATE feedback
            SET rating = ?,
                comments = ?
            WHERE feedback_id = ?
              AND user_id = ?
        ");

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {

            $stmt->bind_param(
                "isii",
                $rating,
                $comments,
                $feedback_id,
                $user_id
            );

            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: /eventmanagements/student/feedback.php?success=feedback_updated"
                );

                exit();

            } else {
                $error = "Unable to update feedback: " . $stmt->error;
            }

            $stmt->close();
        }
    }

    $feedback["rating"] = $rating;
    $feedback["comments"] = $comments;
}

require_once __DIR__ . "/../includes/header.php";

?>

<div class="auth-card">

    <h2>Update Feedback</h2>

    <p class="text-muted">
        Event:
        <?php
        echo htmlspecialchars(
            $feedback["title"],
            ENT_QUOTES,
            "UTF-8"
        );
        ?>
    </p>

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

    <form
        method="POST"
        action=""
    >

        <div class="form-group">

            <label for="rating">Rating</label>

            <select
                name="rating"
                id="rating"
                required
            >

                <option value="">Select Rating</option>

                <?php for ($i = 1; $i <= 5; $i++): ?>

                    <option
                        value="<?php echo $i; ?>"
                        <?php
                        echo (
                            (int) $feedback["rating"] === $i
                        ) ? "selected" : "";
                        ?>
                    >
                        <?php echo $i; ?>
                        Star<?php echo $i > 1 ? "s" : ""; ?>
                    </option>

                <?php endfor; ?>

            </select>

        </div>

        <div class="form-group">

            <label for="comments">Your Feedback</label>

            <textarea
                name="comments"
                id="comments"
                rows="5"
                maxlength="1000"
                required
            ><?php
            echo htmlspecialchars(
                $feedback["comments"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?></textarea>

        </div>

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Update Feedback
            </button>

            <a
                href="/eventmanagements/student/feedback.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </div>

    </form>

</div>

<?php
require_once __DIR__ . "/../includes/footer.php";
?>