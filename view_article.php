<?php
// File: view_text.php
// Purpose: Displays the full content of a single text.

session_start();

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- Includes ---
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';
require_once 'includes/Parsedown.php';

// --- Logic ---
$username = htmlspecialchars($_SESSION['username']);
$user_role = $_SESSION['user_role'];
$current_theme = getCurrentTheme($pdo);

$article = null;
$error_message = '';

$article_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$article_id || $article_id <= 0) {
    $error_message = "Invalid article ID provided.";
} else {
    try {
        // Fetch the latest revision of the article along with editor info
        $sql = "
            SELECT
                a.title,
                r.content,
                r.created_at AS revision_date,
                u.username AS editor_name
            FROM articles AS a
            JOIN revisions AS r ON a.id = r.article_id
            LEFT JOIN users AS u ON r.editor_id = u.id
            WHERE a.id = ?
            ORDER BY r.id DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$article_id]);
        $article = $stmt->fetch();

        if (!$article) {
            $error_message = "The requested article could not be found.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $article ? htmlspecialchars($article['title']) : 'View Article'; ?> - Study Platform</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .theme-switcher { display: flex; align-items: center; }
        .theme-switcher select { margin: 0 0.5rem; padding: 0.25rem 0.5rem; }
        .theme-switcher button { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
        .content-body { /* line-height: 1.6; white-space: pre-wrap; */ }
        .back-link { display: inline-block; margin-bottom: 2rem; }
        .error { color: #721c24; background-color: #f8d7da; padding: 1rem; border-radius: 4px; }
        .meta-info { font-size: 0.9em; color: #6c757d; margin-bottom: 1.5rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>!</span>
        <div>
            <form action="includes/theme_manager.php" method="POST" class="theme-switcher">
                <input type="hidden" name="redirect_url" value="view_article.php?id=<?php echo $article_id; ?>">
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
        <a href="<?php echo $user_role === 'teacher' ? 'admin_dashboard.php' : 'student_dashboard.php'; ?>" class="back-link">&larr; Back to Dashboard</a>

        <?php if ($error_message): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php elseif ($article): ?>
            <div class="article-content">
                <h1><?php echo htmlspecialchars($article['title']); ?></h1>
                <div class="meta-info">
                    Last updated on <?php echo date('F j, Y, g:i a', strtotime($article['revision_date'])); ?>
                    by <strong><?php echo htmlspecialchars($article['editor_name'] ?? 'Unknown'); ?></strong>
                </div>
                <div class="content-body">
                    <?php
                        $Parsedown = new Parsedown();
                        $Parsedown->setSafeMode(true); // Sanitize user input
                        echo $Parsedown->text($article['content']);
                    ?>

                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
