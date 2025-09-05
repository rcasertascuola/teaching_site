<?php
// File: manage_users.php
// Purpose: Allows teachers to view and manage student users.

session_start();

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$current_theme = getCurrentTheme($pdo);
$username = $_SESSION['username'];
$users = [];

// Fetch all student users from the database
try {
    $stmt = $pdo->query("SELECT id, username, email, classe, anno_scolastico, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = '<div class="message error">Could not fetch users: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th, .user-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .user-table th { background-color: #f2f2f2; }
        /* Adjust dark theme table header */
        .dark-theme .user-table th { background-color: #333; }
    </style>
</head>
<body class="<?php echo $current_theme; ?>-theme">
    <div class="navbar">
        <span>User Management</span>
        <a href="admin_dashboard.php">Back to Dashboard</a>
    </div>

    <div class="container">
        <h1>Manage Student Users</h1>

        <?php if (isset($message)) echo $message; ?>

        <div class="table-container">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Class</th>
                        <th>School Year</th>
                        <th>Registered On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6">No student users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['classe'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['anno_scolastico'] ?? 'N/A'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
