<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("admin");

$admin_id = (int) $_SESSION["user_id"];

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user_id = (int) ($_POST["user_id"] ?? 0);
    $new_role = $_POST["role"] ?? "";

    if ($user_id <= 0) {
        $error = "Invalid user.";
    } elseif (!in_array($new_role, ["student", "organizer"], true)) {
        $error = "Invalid role.";
    } elseif ($user_id === $admin_id) {
        $error = "You cannot change your own admin role.";
    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET role = ?
            WHERE user_id = ?
            AND role != 'admin'
        ");

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {

            $stmt->bind_param("si", $new_role, $user_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message = "User role updated successfully.";
                } else {
                    $error = "No changes were made. Check the selected user.";
                }
            } else {
                $error = "User role could not be updated.";
            }

            $stmt->close();
        }
    }
}

$users = $conn->query("
    SELECT user_id, name, email, role, created_at
    FROM users
    ORDER BY created_at DESC
");

if (!$users) {
    $error = "Unable to load users: " . $conn->error;
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="dashboard-header">
    <div>
        <h1>Manage Users</h1>
        <p>View users and manage their roles.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($users && $users->num_rows > 0): ?>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int) $user["user_id"] ?></td>
                        <td><?= htmlspecialchars($user["name"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <span class="status status-<?= htmlspecialchars($user["role"], ENT_QUOTES, "UTF-8") ?>">
                                <?= htmlspecialchars(ucfirst($user["role"]), ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($user["created_at"], ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <?php if ($user["role"] === "admin"): ?>
                                <span>Protected</span>
                            <?php else: ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="user_id" value="<?= (int) $user["user_id"] ?>">
                                    <select name="role" required>
                                        <option value="student" <?= $user["role"] === "student" ? "selected" : "" ?>>Student</option>
                                        <option value="organizer" <?= $user["role"] === "organizer" ? "selected" : "" ?>>Organizer</option>
                                    </select>
                                    <button type="submit" class="btn btn-small">Update</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<br>

<a href="/eventmanagements/admin/dashboard.php" class="btn">← Back to Dashboard</a>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>