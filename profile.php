<?php
// File: profile.php
// Purpose: Allows users to edit their profile information (e.g., change password).

session_start();

// --- Security Check: Ensure user is logged in ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$message = '';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$current_theme = getCurrentTheme($pdo);

// --- Handle Password Change Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = '<div class="message error">Please fill in all password fields.</div>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<div class="message error">New passwords do not match.</div>';
    } else {
        try {
            // 1. Fetch the user's current hashed password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // 2. Verify the current password
            if ($user && password_verify($current_password, $user['password'])) {
                // 3. Hash the new password and update the database
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");

                if ($update_stmt->execute([$new_hashed_password, $user_id])) {
                    $message = '<div class="message success">Password updated successfully!</div>';
                } else {
                    $message = '<div class="message error">Failed to update password.</div>';
                }
            } else {
                $message = '<div class="message error">Incorrect current password.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
</head>
<body>
    <div class="navbar">
        <span><?php echo htmlspecialchars($username); ?>'s Profile</span>
        <a href="<?php echo $_SESSION['user_role'] === 'teacher' ? 'admin_dashboard.php' : 'student_dashboard.php'; ?>">Back to Dashboard</a>
    </div>

    <div class="container">
        <h1>Edit Profile</h1>

        <?php echo $message; ?>

        <div class="form-container">
            <h2>Change Password</h2>
            <form action="profile.php" method="POST">
                <input type="hidden" name="change_password" value="1">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit">Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>
