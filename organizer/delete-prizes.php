<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("organizer");

$organizer_id = (int) $_SESSION["user_id"];

$prize_id = (int) (
    $_GET["id"]
    ?? $_POST["prize_id"]
    ?? 0
);

if ($prize_id <= 0) {
    header("Location: /eventmanagements/organizer/dashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Get Prize and Verify Organizer Ownership
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        p.prize_id,
        p.event_id,
        p.position,
        p.amount,
        e.title
    FROM prizes p
    INNER JOIN events e
        ON e.event_id = p.event_id
    WHERE p.prize_id = ?
      AND e.organizer_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Prize query failed: " . $conn->error);
}

$stmt->bind_param("ii", $prize_id, $organizer_id);
$stmt->execute();

$prize = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$prize) {
    header("Location: /eventmanagements/organizer/dashboard.php");
    exit();
}

$error = "";

/*
|--------------------------------------------------------------------------
| Delete Prize After POST Confirmation
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $delete_stmt = $conn->prepare("
        DELETE p
        FROM prizes p
        INNER JOIN events e
            ON e.event_id = p.event_id
        WHERE p.prize_id = ?
          AND e.organizer_id = ?
    ");

    if (!$delete_stmt) {
        $error = "Delete query failed: " . $conn->error;
    } else {

        $delete_stmt->bind_param(
            "ii",
            $prize_id,
            $organizer_id
        );

        if ($delete_stmt->execute()) {

            if ($delete_stmt->affected_rows === 1) {

                $event_id = (int) $prize["event_id"];

                $delete_stmt->close();

                header(
                    "Location: /eventmanagements/organizer/manage-prizes.php?id=" .
                    $event_id
                );

                exit();

            } else {
                $error = "Prize could not be deleted.";
            }

        } else {
            $error = "Failed to delete prize: " . $delete_stmt->error;
        }

        $delete_stmt->close();
    }
}

require_once __DIR__ . "/../includes/header.php";

?>

<section class="form-container">

    <h1>Delete Prize</h1>

    <?php if (!empty($error)): ?>

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

    <div class="alert error">

        <p>
            Are you sure you want to delete this prize?
        </p>

        <p>
            <strong>
                <?php
                echo htmlspecialchars(
                    $prize["title"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </strong>
        </p>

        <p>
            <strong>Position:</strong>
            <?php echo (int) $prize["position"]; ?>
        </p>

        <p>
            <strong>Amount:</strong>
            ₹<?php echo number_format((float) $prize["amount"], 2); ?>
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
            name="prize_id"
            value="<?php echo (int) $prize["prize_id"]; ?>"
        >

        <button
            type="submit"
            class="btn btn-danger"
        >
            Yes, Delete Prize
        </button>

        <a
            href="/eventmanagements/organizer/manage-prizes.php?id=<?php echo (int) $prize["event_id"]; ?>"
            class="btn"
        >
            Cancel
        </a>

    </form>

</section>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>