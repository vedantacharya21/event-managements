<?php

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

$search = trim($_GET["search"] ?? "");
$event_type = $_GET["event_type"] ?? "";

$allowed_event_types = ["online", "offline", "hybrid"];

if (!in_array($event_type, $allowed_event_types, true)) {
    $event_type = "";
}

$category_id = (int) ($_GET["category_id"] ?? 0);

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
        u.name AS organizer_name,
        (
            SELECT COUNT(*)
            FROM registrations r
            WHERE r.event_id = e.event_id
              AND r.status = 'registered'
        ) AS registered_count
    FROM events e
    INNER JOIN users u ON e.organizer_id = u.user_id
    WHERE e.status = 'approved'
";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ?) ";
    $search_value = "%" . $search . "%";
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "sss";
}

if ($event_type !== "") {
    $sql .= " AND e.event_type = ? ";
    $params[] = $event_type;
    $types .= "s";
}

if ($category_id > 0) {
    $sql .= " AND EXISTS (
        SELECT 1 FROM event_categories ec
        WHERE ec.event_id = e.event_id AND ec.category_id = ?
    ) ";
    $params[] = $category_id;
    $types .= "i";
}

$sql .= " ORDER BY e.event_date ASC, e.event_time ASC ";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Failed to prepare event query: " . htmlspecialchars($conn->error, ENT_QUOTES, "UTF-8"));
}

if (!empty($params)) {
    $bind_values = [];
    $bind_values[] = $types;
    foreach ($params as $key => $value) {
        $bind_values[] = &$params[$key];
    }
    if (!call_user_func_array([$stmt, "bind_param"], $bind_values)) {
        die("Failed to bind event query parameters.");
    }
}

if (!$stmt->execute()) {
    die("Failed to execute event query: " . htmlspecialchars($stmt->error, ENT_QUOTES, "UTF-8"));
}

$result = $stmt->get_result();
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

$event_types = [
    "online" => "Online",
    "offline" => "Offline",
    "hybrid" => "Hybrid"
];

require_once __DIR__ . "/includes/header.php";

?>

<section class="hero">
    <h1>College Events</h1>
    <p>Discover and register for upcoming college events.</p>
</section>

<div class="card">

    <h2>Find Events</h2>

    <form method="GET" action="/eventmanagements/index.php" class="filter-form">

        <div>
            <label for="search">Search Events</label>
            <input
                type="text"
                id="search"
                name="search"
                placeholder="Search by title, venue..."
                value="<?php echo htmlspecialchars($search, ENT_QUOTES, "UTF-8"); ?>"
            >
        </div>

        <div>
            <label for="event_type">Event Type</label>
            <select name="event_type" id="event_type">
                <option value="">All Types</option>
                <?php foreach ($event_types as $value => $label): ?>
                    <option
                        value="<?php echo htmlspecialchars($value, ENT_QUOTES, "UTF-8"); ?>"
                        <?php echo ($event_type === $value) ? "selected" : ""; ?>
                    >
                        <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="category_id">Category</label>
            <select name="category_id" id="category_id">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <?php $current_category_id = (int) $category["category_id"]; ?>
                    <option
                        value="<?php echo $current_category_id; ?>"
                        <?php echo ($category_id === $current_category_id) ? "selected" : ""; ?>
                    >
                        <?php echo htmlspecialchars($category["name"], ENT_QUOTES, "UTF-8"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <button type="submit" class="btn">Search</button>
        </div>

        <div>
            <a href="/eventmanagements/index.php" class="btn btn-secondary">Clear</a>
        </div>

    </form>

</div>

<div class="page-header">
    <h2>Available Events</h2>
    <p><?php echo count($events); ?> event(s) found.</p>
</div>

<?php if (empty($events)): ?>

    <div class="empty-state">
        <h3>No Approved Events Found</h3>
        <p>There are currently no events matching your search criteria.</p>
        <a href="/eventmanagements/index.php" class="btn">View All Events</a>
    </div>

<?php else: ?>

    <div class="event-grid">

        <?php foreach ($events as $event): ?>

            <?php
            $registered_count = (int) $event["registered_count"];
            $capacity = (int) $event["capacity"];
            $available_seats = max(0, $capacity - $registered_count);

            $poster_name = "";
            if (!empty($event["poster"])) {
                $poster_name = basename($event["poster"]);
            }

            $description = $event["description"] ?? "";
            if (strlen($description) > 150) {
                $description = substr($description, 0, 150) . "...";
            }

            $event_type_label = $event_types[$event["event_type"]] ?? ucfirst($event["event_type"]);
            ?>

            <article class="event-card">

                <?php if ($poster_name !== ""): ?>
                    <img
                        src="/eventmanagements/uploads/posters/<?php echo rawurlencode($poster_name); ?>"
                        alt="<?php echo htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8"); ?>"
                    >
                <?php else: ?>
                    <div
                        class="event-poster-placeholder"
                        style="height:190px;display:flex;align-items:center;justify-content:center;background:#e5e7eb;color:#6b7280;"
                    >
                        No Poster Available
                    </div>
                <?php endif; ?>

                <div class="event-card-content">

                    <h3><?php echo htmlspecialchars($event["title"], ENT_QUOTES, "UTF-8"); ?></h3>

                    <p><?php echo htmlspecialchars($description, ENT_QUOTES, "UTF-8"); ?></p>

                    <p><strong>Date:</strong> <?php echo date("d M Y", strtotime($event["event_date"])); ?></p>
                    <p><strong>Time:</strong> <?php echo date("h:i A", strtotime($event["event_time"])); ?></p>
                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event["venue"], ENT_QUOTES, "UTF-8"); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($event_type_label, ENT_QUOTES, "UTF-8"); ?></p>
                    <p><strong>Organizer:</strong> <?php echo htmlspecialchars($event["organizer_name"], ENT_QUOTES, "UTF-8"); ?></p>

                    <p>
                        <strong>Registration:</strong>
                        <?php if ((float) $event["registration_fee"] <= 0): ?>
                            Free
                        <?php else: ?>
                            ₹<?php echo number_format((float) $event["registration_fee"], 2); ?>
                        <?php endif; ?>
                    </p>

                    <p><strong>Seats:</strong> <?php echo $registered_count; ?> / <?php echo $capacity; ?></p>
                    <p><strong>Available:</strong> <?php echo $available_seats; ?></p>

                    <?php if ($available_seats > 0): ?>
                        <span class="status status-approved">Seats Available</span>
                    <?php else: ?>
                        <span class="status status-rejected">Full</span>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <a href="/eventmanagements/event.php?id=<?php echo (int) $event["event_id"]; ?>" class="btn">
                            View Details
                        </a>
                    </div>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<?php require_once __DIR__ . "/includes/footer.php"; ?>