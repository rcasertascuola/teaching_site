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
$user_id = $_SESSION['user_id'];
$current_theme = getCurrentTheme($pdo);

$message = '';
try {
    // Fetch articles
    $sql_articles = "
        SELECT a.id, a.title, a.created_at, SUBSTRING(r.content, 1, 150) AS snippet
        FROM articles AS a
        INNER JOIN revisions AS r ON r.id = (
            SELECT MAX(id) FROM revisions WHERE article_id = a.id
        )
        ORDER BY a.created_at DESC";
    $stmt_articles = $pdo->query($sql_articles);
    $articles = $stmt_articles->fetchAll();

    // Fetch exercises and check for submissions
    $sql_exercises = "
        SELECT
            e.id,
            e.title,
            e.created_at,
            (SELECT COUNT(*) FROM student_submissions WHERE exercise_id = e.id AND student_id = :user_id) AS submission_count
        FROM exercises AS e
        ORDER BY e.created_at DESC";
    $stmt_exercises = $pdo->prepare($sql_exercises);
    $stmt_exercises->execute(['user_id' => $user_id]);
    $exercises = $stmt_exercises->fetchAll();

} catch (PDOException $e) {
    $articles = [];
    $exercises = [];
    $message = '<div class="message error">Could not fetch data: ' . $e->getMessage() . '</div>';
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

        <hr>

        <h2>Available Exercises</h2>
        <div class="text-list">
            <?php if (empty($exercises)): ?>
                <p>No exercises are available at the moment.</p>
            <?php else: ?>
                <?php foreach ($exercises as $exercise): ?>
                    <div class="text-list-item">
                        <h3><?php echo htmlspecialchars($exercise['title']); ?></h3>
                        <p>Created on: <?php echo date('Y-m-d', strtotime($exercise['created_at'])); ?></p>
                        <?php if ($exercise['submission_count'] > 0): ?>
                            <a href="view_submission.php?exercise_id=<?php echo $exercise['id']; ?>" class="button-submitted">View Submission</a>
                        <?php else: ?>
                            <a href="view_exercise.php?id=<?php echo $exercise['id']; ?>">Start Exercise</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
