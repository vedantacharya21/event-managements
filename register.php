<?php

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

if (isLoggedIn()) {
    redirectByRole();
}

$error = "";
$success = "";
$name = "";
$email = "";
$role = "student";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $role = $_POST["role"] ?? "student";

    if (!in_array($role, ["student", "organizer"], true)) {
        $role = "student";
    }

    if ($name === "" || $email === "" || $password === "" || $confirm_password === "") {
        $error = "Please fill in all fields.";
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error = "Name must be between 2 and 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {

        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");

        if (!$stmt) {
            $error = "Something went wrong. Please try again.";
        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "An account with this email already exists.";
                $stmt->close();
            } else {

                $stmt->close();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (name, email, password, role)
                    VALUES (?, ?, ?, ?)
                ");

                if (!$stmt) {
                    $error = "Registration failed. Please try again.";
                } else {

                    $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

                    if ($stmt->execute()) {
                        $success = "Registration successful. You can now login.";
                        $name = "";
                        $email = "";
                    } else {
                        if ($stmt->errno === 1062) {
                            $error = "An account with this email already exists.";
                        } else {
                            $error = "Registration failed. Please try again.";
                        }
                    }

                    $stmt->close();
                }
            }
        }
    }
}

require_once __DIR__ . "/includes/header.php";
?>

<div class="auth-container">
    <div class="auth-card">

        <h1>Create Account</h1>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/eventmanagements/register.php">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>"
                    required
                    minlength="2"
                    maxlength="100"
                    autocomplete="name"
                >
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="role">Register As</label>
                <select id="role" name="role" required>
                    <option value="student" <?php echo $role === "student" ? "selected" : ""; ?>>Student</option>
                    <option value="organizer" <?php echo $role === "organizer" ? "selected" : ""; ?>>Organizer</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    minlength="6"
                    autocomplete="new-password"
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    required
                    minlength="6"
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="btn btn-primary btn-block">Register</button>

        </form>

        <p class="text-center mt-20">
            Already have an account?
            <a href="/eventmanagements/login.php">Login here</a>
        </p>

    </div>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>