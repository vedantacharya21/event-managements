<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("organizer");

$organizer_id = (int) $_SESSION["user_id"];

$errors = [];
$success = "";

$title = "";
$description = "";
$event_date = "";
$event_time = "";
$venue = "";
$capacity = "";
$registration_fee = "0";
$event_type = "offline";
$selected_categories = [];

/*
|--------------------------------------------------------------------------
| Event Types
|--------------------------------------------------------------------------
*/

$event_types = [
    "online" => "Online",
    "offline" => "Offline",
    "hybrid" => "Hybrid"
];

/*
|--------------------------------------------------------------------------
| Get Categories
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

} else {

    $errors[] = "Unable to load categories.";

}

/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $event_date = $_POST["event_date"] ?? "";
    $event_time = $_POST["event_time"] ?? "";
    $venue = trim($_POST["venue"] ?? "");

    $capacity_input = $_POST["capacity"] ?? "";
    $capacity = (int) $capacity_input;

    $registration_fee_input = $_POST["registration_fee"] ?? "0";
    $registration_fee = (float) $registration_fee_input;

    $event_type = $_POST["event_type"] ?? "offline";

    $selected_categories = $_POST["categories"] ?? [];

    if (!is_array($selected_categories)) {
        $selected_categories = [];
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($title === "") {
        $errors[] = "Event title is required.";
    }

    if ($description === "") {
        $errors[] = "Event description is required.";
    }

    if ($event_date === "") {

        $errors[] = "Event date is required.";

    } elseif ($event_date < date("Y-m-d")) {

        $errors[] = "Event date cannot be in the past.";

    }

    if ($event_time === "") {
        $errors[] = "Event time is required.";
    }

    if ($venue === "") {
        $errors[] = "Venue is required.";
    }

    if ($capacity <= 0) {
        $errors[] = "Capacity must be greater than 0.";
    }

    if ($registration_fee < 0) {
        $errors[] = "Registration fee cannot be negative.";
    }

    if (!array_key_exists($event_type, $event_types)) {
        $errors[] = "Invalid event type.";
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Categories
    |--------------------------------------------------------------------------
    */

    $valid_category_ids = [];

    foreach ($categories as $category) {
        $valid_category_ids[] = (int) $category["category_id"];
    }

    $selected_categories = array_map(
        "intval",
        $selected_categories
    );

    $selected_categories = array_values(
        array_unique($selected_categories)
    );

    foreach ($selected_categories as $category_id) {

        if (!in_array(
            $category_id,
            $valid_category_ids,
            true
        )) {

            $errors[] = "Invalid category selected.";
            break;

        }
    }

    /*
    |--------------------------------------------------------------------------
    | Poster Upload
    |--------------------------------------------------------------------------
    */

    $poster_name = null;
    $uploaded_poster_path = null;

    if (
        isset($_FILES["poster"]) &&
        $_FILES["poster"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["poster"]["error"] !== UPLOAD_ERR_OK) {

            $errors[] = "Poster upload failed.";

        } else {

            $file = $_FILES["poster"];
            $max_size = 5 * 1024 * 1024;

            if ($file["size"] > $max_size) {

                $errors[] = "Poster size must be less than 5 MB.";

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

                $allowed_mime_types = [
                    "image/jpeg",
                    "image/png",
                    "image/webp"
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file["tmp_name"]);

                if (
                    !in_array(
                        $extension,
                        $allowed_extensions,
                        true
                    ) ||
                    !in_array(
                        $mime_type,
                        $allowed_mime_types,
                        true
                    )
                ) {

                    $errors[] =
                        "Poster must be a valid JPG, JPEG, PNG or WEBP image.";

                } else {

                    $upload_directory =
                        __DIR__ . "/../uploads/posters/";

                    if (!is_dir($upload_directory)) {

                        if (!mkdir(
                            $upload_directory,
                            0755,
                            true
                        )) {

                            $errors[] =
                                "Unable to create upload directory.";

                        }
                    }

                    if (empty($errors)) {

                        $poster_name =
                            bin2hex(random_bytes(16)) .
                            "." .
                            $extension;

                        $uploaded_poster_path =
                            $upload_directory . $poster_name;

                        if (!move_uploaded_file(
                            $file["tmp_name"],
                            $uploaded_poster_path
                        )) {

                            $errors[] = "Unable to save poster.";

                            $poster_name = null;
                            $uploaded_poster_path = null;

                        }
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Insert Event
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $conn->begin_transaction();

        try {

            $event_sql = "
                INSERT INTO events (
                    title,
                    description,
                    poster,
                    event_date,
                    event_time,
                    venue,
                    capacity,
                    registration_fee,
                    event_type,
                    status,
                    organizer_id
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?
                )
            ";

            $stmt = $conn->prepare($event_sql);

            if (!$stmt) {
                throw new Exception(
                    "Failed to prepare event query: " .
                    $conn->error
                );
            }

            $stmt->bind_param(
                "ssssssidsi",
                $title,
                $description,
                $poster_name,
                $event_date,
                $event_time,
                $venue,
                $capacity,
                $registration_fee,
                $event_type,
                $organizer_id
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Failed to create event: " .
                    $stmt->error
                );

            }

            $event_id = $conn->insert_id;

            $stmt->close();

            /*
            |--------------------------------------------------------------------------
            | Add Categories
            |--------------------------------------------------------------------------
            */

            if (!empty($selected_categories)) {

                $category_stmt = $conn->prepare("
                    INSERT INTO event_categories (
                        event_id,
                        category_id
                    )
                    VALUES (?, ?)
                ");

                if (!$category_stmt) {

                    throw new Exception(
                        "Failed to prepare category query: " .
                        $conn->error
                    );

                }

                foreach ($selected_categories as $category_id) {

                    if ($category_id <= 0) {
                        continue;
                    }

                    $category_stmt->bind_param(
                        "ii",
                        $event_id,
                        $category_id
                    );

                    if (!$category_stmt->execute()) {

                        throw new Exception(
                            "Failed to add event category: " .
                            $category_stmt->error
                        );

                    }
                }

                $category_stmt->close();
            }

            $conn->commit();

            $success =
                "Event created successfully and sent for admin approval.";

            $title = "";
            $description = "";
            $event_date = "";
            $event_time = "";
            $venue = "";
            $capacity = "";
            $registration_fee = "0";
            $event_type = "offline";
            $selected_categories = [];

        } catch (Exception $e) {

            $conn->rollback();

            if (
                $uploaded_poster_path !== null &&
                file_exists($uploaded_poster_path)
            ) {

                unlink($uploaded_poster_path);

            }

            $errors[] = $e->getMessage();

        }
    }
}

require_once __DIR__ . "/../includes/header.php";

?>

<div class="dashboard-header">

    <div>

        <h1>Create New Event</h1>

        <p>
            Submit an event for admin approval.
        </p>

    </div>

</div>

<?php if (!empty($errors)): ?>

    <div class="alert error">

        <h3>Please fix the following:</h3>

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
    class="event-form"
>

    <div class="form-group">

        <label for="title">
            Event Title *
        </label>

        <input
            type="text"
            id="title"
            name="title"
            value="<?php
            echo htmlspecialchars(
                $title,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>"
            maxlength="150"
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

        <label for="poster">
            Event Poster
        </label>

        <input
            type="file"
            id="poster"
            name="poster"
            accept=".jpg,.jpeg,.png,.webp"
        >

        <small>
            Maximum size: 5 MB. JPG, JPEG, PNG or WEBP.
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
            min="<?php echo date("Y-m-d"); ?>"
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
            value="<?php
            echo htmlspecialchars(
                $venue,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>"
            maxlength="200"
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
            value="<?php
            echo htmlspecialchars(
                (string) $capacity,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>"
            min="1"
            required
        >

    </div>

    <div class="form-group">

        <label for="registration_fee">
            Registration Fee (₹)
        </label>

        <input
            type="number"
            id="registration_fee"
            name="registration_fee"
            value="<?php
            echo htmlspecialchars(
                (string) $registration_fee,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>"
            min="0"
            step="0.01"
        >

        <small>
            Enter 0 if the event is free.
        </small>

    </div>

    <div class="form-group">

        <label for="event_type">
            Event Type *
        </label>

        <select
            id="event_type"
            name="event_type"
            required
        >

            <?php foreach ($event_types as $value => $label): ?>

                <option
                    value="<?php echo htmlspecialchars(
                        $value,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    <?php
                    echo $event_type === $value
                        ? "selected"
                        : "";
                    ?>
                >

                    <?php
                    echo htmlspecialchars(
                        $label,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>

    <div class="form-group">

        <label>
            Categories
        </label>

        <p>
            Select one or more categories.
        </p>

        <?php if (!empty($categories)): ?>

            <?php foreach ($categories as $category): ?>

                <?php

                $category_id = (int) $category["category_id"];

                $is_selected = in_array(
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
                        <?php
                        echo $is_selected
                            ? "checked"
                            : "";
                        ?>
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

        <?php else: ?>

            <p>
                No categories available.
            </p>

        <?php endif; ?>

    </div>

    <div class="form-actions">

        <button
            type="submit"
            class="btn"
        >
            Create Event
        </button>

        <a
            href="/eventmanagements/organizer/dashboard.php"
            class="btn"
        >
            Cancel
        </a>

    </div>

</form>

<?php

require_once __DIR__ . "/../includes/footer.php";

?>