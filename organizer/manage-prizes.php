<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";

requireRole("organizer");

$organizer_id = (int) $_SESSION["user_id"];

$event_id = (int) (
    $_GET["id"]
    ?? $_GET["event_id"]
    ?? $_POST["event_id"]
    ?? 0
);

if ($event_id <= 0) {
    header("Location: /eventmanagements/organizer/dashboard.php");
    exit();
}

// Verify event ownership
$stmt = $conn->prepare("
    SELECT event_id, title, event_type, status
    FROM events
    WHERE event_id = ?
      AND organizer_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("ii", $event_id, $organizer_id);
$stmt->execute();

$result = $stmt->get_result();
$event = $result->fetch_assoc();

$stmt->close();

if (!$event) {
    header("Location: /eventmanagements/organizer/dashboard.php");
    exit();
}

$message = "";
$error = "";

// Add prize
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $position = (int) ($_POST["position"] ?? 0);
    $amount = (float) ($_POST["amount"] ?? 0);
    $description = trim($_POST["description"] ?? "");

    if ($position <= 0) {
        $error = "Position must be greater than 0.";

    } elseif ($amount < 0) {
        $error = "Prize amount cannot be negative.";

    } elseif (mb_strlen($description) > 255) {
        $error = "Description cannot exceed 255 characters.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO prizes (
                event_id,
                position,
                amount,
                description
            )
            VALUES (?, ?, ?, ?)
        ");

        if (!$stmt) {
            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "iids",
                $event_id,
                $position,
                $amount,
                $description
            );

            if ($stmt->execute()) {
                $message = "Prize added successfully.";
            } elseif ($stmt->errno === 1062) {
                $error = "This position already exists for this event.";
            } else {
                $error = "Failed to add prize: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// Fetch existing prizes
$stmt = $conn->prepare("
    SELECT
        prize_id,
        event_id,
        position,
        amount,
        description
    FROM prizes
    WHERE event_id = ?
    ORDER BY position ASC
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $event_id);
$stmt->execute();

$prizes = $stmt->get_result();

$stmt->close();

require_once __DIR__ . "/../includes/header.php";

?>

<section>

    <div class="dashboard-header">

        <div>
            <h1>Manage Prizes</h1>

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
                Event ID:
                <strong><?php echo $event_id; ?></strong>
            </p>

            <p>
                Event Type:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        ucfirst($event["event_type"]),
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </strong>
            </p>

            <p>
                Status:
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

    </div>

    <?php if ($message !== ""): ?>
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

    <div class="form-container">

        <h3>Add Prize</h3>

        <form method="POST">

            <input
                type="hidden"
                name="event_id"
                value="<?php echo $event_id; ?>"
            >

            <div class="form-group">

                <label for="position">
                    Position *
                </label>

                <input
                    type="number"
                    id="position"
                    name="position"
                    min="1"
                    required
                >

            </div>

            <div class="form-group">

                <label for="amount">
                    Prize Amount (₹) *
                </label>

                <input
                    type="number"
                    id="amount"
                    name="amount"
                    min="0"
                    step="0.01"
                    value="0"
                    required
                >

            </div>

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <input
                    type="text"
                    id="description"
                    name="description"
                    maxlength="255"
                    placeholder="Example: Trophy + Certificate"
                >

            </div>

            <button
                type="submit"
                class="btn"
            >
                Add Prize
            </button>

        </form>

    </div>

    <br>

    <section>

        <h3>Existing Prizes</h3>

        <?php if ($prizes->num_rows === 0): ?>

            <div class="empty-state">
                <p>No prizes added yet.</p>
            </div>

        <?php else: ?>

            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($prize = $prizes->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php
                                    echo (int) $prize["position"];
                                    ?>
                                </td>

                                <td>
                                    ₹<?php
                                    echo number_format(
                                        (float) $prize["amount"],
                                        2
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $prize["description"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>
                                </td>

                                <td>

                                    <a
                                        href="/eventmanagements/organizer/delete-prizes.php?id=<?php echo (int) $prize["prize_id"]; ?>&event_id=<?php echo $event_id; ?>"
                                        class="btn btn-small btn-danger"
                                        data-confirm="Are you sure you want to delete this prize?"
                                    >
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

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