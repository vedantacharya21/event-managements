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
| Get Event
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        event_id,
        title,
        description,
        poster,
        event_date,
        event_time,
        venue,
        capacity,
        registration_fee,
        event_type,
        status
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
    die("Event not found or you do not have permission to edit it.");
}

/*
|--------------------------------------------------------------------------
| Initialize Form Values
|--------------------------------------------------------------------------
*/

$title = $event["title"];
$description = $event["description"];
$event_date = $event["event_date"];
$event_time = $event["event_time"];
$venue = $event["venue"];
$capacity = (int) $event["capacity"];
$registration_fee = (float) $event["registration_fee"];
$event_type = $event["event_type"];
$current_poster = $event["poster"];

$errors = [];
$success = "";

/*
|--------------------------------------------------------------------------
| Current Registered Participants
|--------------------------------------------------------------------------
*/

$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM registrations
    WHERE event_id = ?
      AND status = 'registered'
");

if (!$count_stmt) {
    die("Registration count query failed: " . $conn->error);
}

$count_stmt->bind_param("i", $event_id);
$count_stmt->execute();

$count_data = $count_stmt->get_result()->fetch_assoc();

$registered_count = (int) ($count_data["total"] ?? 0);

$count_stmt->close();

/*
|--------------------------------------------------------------------------
| Get Existing Categories
|--------------------------------------------------------------------------
*/

$selected_categories = [];

$cat_stmt = $conn->prepare("
    SELECT category_id
    FROM event_categories
    WHERE event_id = ?
");

if ($cat_stmt) {
    $cat_stmt->bind_param("i", $event_id);
    $cat_stmt->execute();

    $cat_result = $cat_stmt->get_result();

    while ($row = $cat_result->fetch_assoc()) {
        $selected_categories[] = (int) $row["category_id"];
    }

    $cat_stmt->close();
}

/*
|--------------------------------------------------------------------------
| Get All Categories
|--------------------------------------------------------------------------
*/

$categories = [];

$category_result = $conn->query("
    SELECT category_id, name
    FROM categories
    ORDER BY name ASC
");

if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Update Event
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $event_date = $_POST["event_date"] ?? "";
    $event_time = $_POST["event_time"] ?? "";
    $venue = trim($_POST["venue"] ?? "");

    $capacity = (int) ($_POST["capacity"] ?? 0);

    $registration_fee = (float) (
        $_POST["registration_fee"] ?? 0
    );

    $event_type = $_POST["event_type"] ?? "";

    $selected_categories = $_POST["categories"] ?? [];

    if (!is_array($selected_categories)) {
        $selected_categories = [];
    }

    $selected_categories = array_unique(
        array_map("intval", $selected_categories)
    );

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($title === "") {
        $errors[] = "Event title is required.";
    } elseif (mb_strlen($title) > 150) {
        $errors[] = "Event title cannot exceed 150 characters.";
    }

    if ($description === "") {
        $errors[] = "Event description is required.";
    }

    if ($event_date === "") {
        $errors[] = "Event date is required.";
    } else {

        $date_object = DateTime::createFromFormat(
            "Y-m-d",
            $event_date
        );

        if (
            !$date_object ||
            $date_object->format("Y-m-d") !== $event_date
        ) {
            $errors[] = "Please enter a valid event date.";
        }

    }

    if ($event_time === "") {
        $errors[] = "Event time is required.";
    }

    if ($venue === "") {
        $errors[] = "Venue is required.";
    } elseif (mb_strlen($venue) > 200) {
        $errors[] = "Venue cannot exceed 200 characters.";
    }

    if ($capacity <= 0) {
        $errors[] = "Capacity must be greater than 0.";
    }

    if ($capacity < $registered_count) {
        $errors[] =
            "Capacity cannot be lower than the current registered " .
            "participants (" . $registered_count . ").";
    }

    if ($registration_fee < 0) {
        $errors[] = "Registration fee cannot be negative.";
    }

    $allowed_types = [
        "online",
        "offline",
        "hybrid"
    ];

    if (!in_array($event_type, $allowed_types, true)) {
        $errors[] = "Invalid event type.";
    }

    /*
    |--------------------------------------------------------------------------
    | Poster Upload
    |--------------------------------------------------------------------------
    */

    $poster_name = $current_poster;
    $new_poster_uploaded = false;
    $new_poster_path = "";

    if (
        isset($_FILES["poster"]) &&
        $_FILES["poster"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["poster"]["error"] !== UPLOAD_ERR_OK) {

            $errors[] = "Poster upload failed.";

        } else {

            $file = $_FILES["poster"];

            if ($file["size"] > 5 * 1024 * 1024) {

                $errors[] = "Poster must be less than 5 MB.";

            } else {

                $allowed_extensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];

                $extension = strtolower(
                    pathinfo(
                        $file["name"],
                        PATHINFO_EXTENSION
                    )
                );

                if (
                    !in_array(
                        $extension,
                        $allowed_extensions,
                        true
                    )
                ) {

                    $errors[] =
                        "Poster must be JPG, JPEG, PNG or WEBP.";

                } else {

                    $finfo = new finfo(FILEINFO_MIME_TYPE);

                    $mime_type = $finfo->file(
                        $file["tmp_name"]
                    );

                    $allowed_mime_types = [
                        "image/jpeg",
                        "image/png",
                        "image/webp"
                    ];

                    if (
                        !in_array(
                            $mime_type,
                            $allowed_mime_types,
                            true
                        )
                    ) {

                        $errors[] = "Invalid poster file type.";

                    } else {

                        $upload_directory = __DIR__ .
                            "/../uploads/posters/";

                        if (
                            !is_dir($upload_directory) &&
                            !mkdir($upload_directory, 0755, true)
                        ) {

                            $errors[] =
                                "Unable to create poster directory.";

                        } else {

                            $poster_name = uniqid(
                                "poster_",
                                true
                            ) . "." . $extension;

                            $new_poster_path = $upload_directory .
                                $poster_name;

                            if (
                                !move_uploaded_file(
                                    $file["tmp_name"],
                                    $new_poster_path
                                )
                            ) {

                                $errors[] =
                                    "Unable to save the new poster.";

                            } else {

                                $new_poster_uploaded = true;

                            }
                        }
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Changes
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $conn->begin_transaction();

        try {

            $update_stmt = $conn->prepare("
                UPDATE events
                SET
                    title = ?,
                    description = ?,
                    poster = ?,
                    event_date = ?,
                    event_time = ?,
                    venue = ?,
                    capacity = ?,
                    registration_fee = ?,
                    event_type = ?
                WHERE event_id = ?
                  AND organizer_id = ?
            ");

            if (!$update_stmt) {
                throw new Exception(
                    "Update query failed: " . $conn->error
                );
            }

            $update_stmt->bind_param(
                "ssssssidsii",
                $title,
                $description,
                $poster_name,
                $event_date,
                $event_time,
                $venue,
                $capacity,
                $registration_fee,
                $event_type,
                $event_id,
                $organizer_id
            );

            if (!$update_stmt->execute()) {
                throw new Exception(
                    "Failed to update event: " .
                    $update_stmt->error
                );
            }

            $update_stmt->close();

            /*
            |--------------------------------------------------------------------------
            | Replace Categories
            |--------------------------------------------------------------------------
            */

            $delete_cat = $conn->prepare("
                DELETE FROM event_categories
                WHERE event_id = ?
            ");

            if (!$delete_cat) {
                throw new Exception(
                    "Category delete query failed: " . $conn->error
                );
            }

            $delete_cat->bind_param("i", $event_id);

            if (!$delete_cat->execute()) {
                throw new Exception(
                    "Failed to update categories."
                );
            }

            $delete_cat->close();

            if (!empty($selected_categories)) {

                $insert_cat = $conn->prepare("
                    INSERT INTO event_categories
                        (event_id, category_id)
                    VALUES (?, ?)
                ");

                if (!$insert_cat) {
                    throw new Exception(
                        "Category insert query failed: " . $conn->error
                    );
                }

                foreach ($selected_categories as $category_id) {

                    if ($category_id <= 0) {
                        continue;
                    }

                    $insert_cat->bind_param(
                        "ii",
                        $event_id,
                        $category_id
                    );

                    if (!$insert_cat->execute()) {
                        throw new Exception(
                            "Failed to save event categories."
                        );
                    }
                }

                $insert_cat->close();
            }

            $conn->commit();

            /*
            |--------------------------------------------------------------------------
            | Remove Old Poster
            |--------------------------------------------------------------------------
            */

            if (
                $new_poster_uploaded &&
                !empty($current_poster)
            ) {

                $old_file = __DIR__ .
                    "/../uploads/posters/" .
                    basename($current_poster);

                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            $current_poster = $poster_name;

            $success = "Event updated successfully.";

        } catch (Exception $e) {

            $conn->rollback();

            if (
                $new_poster_uploaded &&
                !empty($new_poster_path) &&
                file_exists($new_poster_path)
            ) {
                unlink($new_poster_path);
            }

            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . "/../includes/header.php";

?>

<section class="form-container">

    <div class="dashboard-header">

        <div>

            <h1>Edit Event</h1>

            <p>
                Current Status:

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

    <?php if (!empty($errors)): ?>

        <div class="alert error">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?php
                        echo htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <?php if ($success !== ""): ?>

        <div class="alert success">

            <?php
            echo htmlspecialchars(
                $success,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

        </div>

    <?php endif; ?>

    <form
        method="POST"
        enctype="multipart/form-data"
    >

        <input
            type="hidden"
            name="event_id"
            value="<?php echo $event_id; ?>"
        >

        <div class="form-group">

            <label for="title">
                Event Title *
            </label>

            <input
                type="text"
                id="title"
                name="title"
                maxlength="150"
                value="<?php
                    echo htmlspecialchars(
                        $title,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="description">
                Description *
            </label>

            <textarea
                id="description"
                name="description"
                rows="6"
                required
            ><?php
                echo htmlspecialchars(
                    $description,
                    ENT_QUOTES,
                    "UTF-8"
                );
            ?></textarea>

        </div>

        <div class="form-group">

            <label>Current Poster</label>

            <?php if (!empty($current_poster)): ?>

                <img
                    src="/eventmanagements/uploads/posters/<?php
                        echo rawurlencode(
                            basename($current_poster)
                        );
                    ?>"
                    width="250"
                    alt="Event Poster"
                >

                <br><br>

            <?php else: ?>

                <p>No poster uploaded.</p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label for="poster">
                Replace Poster
            </label>

            <input
                type="file"
                id="poster"
                name="poster"
                accept=".jpg,.jpeg,.png,.webp"
            >

            <small>
                Maximum size: 5 MB.
            </small>

        </div>

        <div class="form-group">

            <label for="event_date">
                Event Date *
            </label>

            <input
                type="date"
                id="event_date"
                name="event_date"
                value="<?php
                    echo htmlspecialchars(
                        $event_date,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="event_time">
                Event Time *
            </label>

            <input
                type="time"
                id="event_time"
                name="event_time"
                value="<?php
                    echo htmlspecialchars(
                        $event_time,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="venue">
                Venue *
            </label>

            <input
                type="text"
                id="venue"
                name="venue"
                maxlength="200"
                value="<?php
                    echo htmlspecialchars(
                        $venue,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="capacity">
                Capacity *
            </label>

            <input
                type="number"
                id="capacity"
                name="capacity"
                min="<?php echo max(1, $registered_count); ?>"
                value="<?php echo $capacity; ?>"
                required
            >

            <small>
                Current registered participants:
                <?php echo $registered_count; ?>
            </small>

        </div>

        <div class="form-group">

            <label for="registration_fee">
                Registration Fee (₹)
            </label>

            <input
                type="number"
                id="registration_fee"
                name="registration_fee"
                min="0"
                step="0.01"
                value="<?php
                    echo htmlspecialchars(
                        (string) $registration_fee,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                ?>"
            >

        </div>

        <div class="form-group">

            <label for="event_type">
                Event Type *
            </label>

            <?php

            $types = [
                "online" => "Online",
                "offline" => "Offline",
                "hybrid" => "Hybrid"
            ];

            ?>

            <select
                id="event_type"
                name="event_type"
                required
            >

                <?php foreach ($types as $value => $label): ?>

                    <option
                        value="<?php echo $value; ?>"
                        <?php
                        echo $event_type === $value
                            ? "selected"
                            : "";
                        ?>
                    >
                        <?php echo $label; ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="form-group">

            <label>
                Categories
            </label>

            <br><br>

            <?php foreach ($categories as $category): ?>

                <?php

                $category_id = (int) $category["category_id"];

                $checked = in_array(
                    $category_id,
                    $selected_categories,
                    true
                );

                ?>

                <label>

                    <input
                        type="checkbox"
                        name="categories[]"
                        value="<?php echo $category_id; ?>"
                        <?php echo $checked ? "checked" : ""; ?>
                    >

                    <?php
                    echo htmlspecialchars(
                        $category["name"],
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </label>

                <br>

            <?php endforeach; ?>

        </div>

        <br>

        <button
            type="submit"
            class="btn"
        >
            Update Event
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