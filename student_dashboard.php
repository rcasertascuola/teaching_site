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
    // This query is more complex because we need to get the latest revision for each article to show a snippet.
    $sql = "
        SELECT
            a.id,
            a.title,
            a.created_at,
            SUBSTRING(r.content, 1, 150) AS snippet
        FROM
            articles AS a
        INNER JOIN
            revisions AS r ON r.id = (
                SELECT MAX(id)
                FROM revisions
                WHERE article_id = a.id
            )
        ORDER BY
            a.created_at DESC
    ";
    $stmt = $pdo->query($sql);
    $articles = $stmt->fetchAll();
} catch (PDOException $e) {
    $articles = [];
    $message = '<div class="message error">Could not fetch articles: ' . $e->getMessage() . '</div>';
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
        .navbar-right { display: flex; align-items: center; gap: 1rem; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>! (Student)</span>
        <div class="navbar-right">
            <form action="includes/theme_manager.php" method="POST" class="theme-switcher">
                <input type="hidden" name="redirect_url" value="student_dashboard.php">
                <input type="hidden" name="change_theme" value="1">
                <select name="theme" onchange="this.form.submit()">
                    <option value="light" <?php echo ($current_theme === 'light') ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo ($current_theme === 'dark') ? 'selected' : ''; ?>>Dark</option>
                </select>
            </form>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>Student Dashboard</h1>

        <p>This is your personal study area. Below is a list of available articles.</p>

        <h2>Available Articles</h2>

        <?php echo $message; ?>

        <div class="text-list">
            <?php if (empty($articles)): ?>
                <p>No articles are available at the moment. Please check back later.</p>
            <?php else: ?>
                <?php foreach ($articles as $article): ?>
                    <div class="text-list-item">
                        <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                        <p><?php echo htmlspecialchars($article['snippet']); ?>...</p>
                        <a href="view_article.php?id=<?php echo $article['id']; ?>">Read More</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
