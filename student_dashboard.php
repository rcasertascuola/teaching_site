<?php
// File: student_dashboard.php
// Purpose: Dashboard for logged-in students.

session_start();

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user_role'] !== 'student') {
    header('Location: admin_dashboard.php'); // Redirect teachers to their dashboard
    exit;
}

// --- Includes ---
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

// --- Data Fetching ---
$username = htmlspecialchars($_SESSION['username']);
$current_theme = getCurrentTheme($pdo);

$message = '';
try {
    $stmt = $pdo->query("SELECT id, title, SUBSTRING(content, 1, 150) as snippet, created_at FROM texts ORDER BY created_at DESC");
    $texts = $stmt->fetchAll();
} catch (PDOException $e) {
    $texts = [];
    $message = '<div class="message error">Could not fetch texts: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        /* Small style for the theme switcher form */
        .theme-switcher { display: flex; align-items: center; }
        .theme-switcher select { margin-right: 0.5rem; padding: 0.25rem 0.5rem; }
        .theme-switcher button { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>! (Student)</span>
        <div>
            <form action="includes/theme_manager.php" method="POST" class="theme-switcher">
                <input type="hidden" name="redirect_url" value="student_dashboard.php">
                <input type="hidden" name="change_theme" value="1">
                <select name="theme">
                    <option value="light" <?php echo ($current_theme === 'light') ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo ($current_theme === 'dark') ? 'selected' : ''; ?>>Dark</option>
                </select>
                <button type="submit">Set</button>
            </form>
        </div>
        <a href="logout.php">Logout</a>
    </div>

    <div class="container">
        <h1>Student Dashboard</h1>

        <p>This is your personal study area. Below is a list of available texts.</p>

        <h2>Available Texts</h2>

        <?php echo $message; ?>

        <div class="text-list">
            <?php if (empty($texts)): ?>
                <p>No texts are available at the moment. Please check back later.</p>
            <?php else: ?>
                <?php foreach ($texts as $text): ?>
                    <div class="text-list-item">
                        <h3><?php echo htmlspecialchars($text['title']); ?></h3>
                        <p><?php echo htmlspecialchars($text['snippet']); ?>...</p>
                        <a href="view_text.php?id=<?php echo $text['id']; ?>">Read More</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
