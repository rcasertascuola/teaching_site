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

// --- Logic ---
$username = htmlspecialchars($_SESSION['username']);
$user_role = $_SESSION['user_role'];
$current_theme = getCurrentTheme($pdo);

$text = null;
$error_message = '';

$text_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$text_id || $text_id <= 0) {
    $error_message = "Invalid text ID provided.";
} else {
    try {
        $stmt = $pdo->prepare("SELECT title, content FROM texts WHERE id = ?");
        $stmt->execute([$text_id]);
        $text = $stmt->fetch();

        if (!$text) {
            $error_message = "The requested text could not be found.";
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
    <title><?php echo $text ? htmlspecialchars($text['title']) : 'View Text'; ?> - Study Platform</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .theme-switcher { display: flex; align-items: center; }
        .theme-switcher select { margin: 0 0.5rem; padding: 0.25rem 0.5rem; }
        .theme-switcher button { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
        .content-body { line-height: 1.6; white-space: pre-wrap; }
        .back-link { display: inline-block; margin-bottom: 2rem; }
        .error { color: #721c24; background-color: #f8d7da; padding: 1rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>!</span>
        <div>
            <form action="includes/theme_manager.php" method="POST" class="theme-switcher">
                <input type="hidden" name="redirect_url" value="view_text.php?id=<?php echo $text_id; ?>">
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
        <?php elseif ($text): ?>
            <div class="text-content">
                <h1><?php echo htmlspecialchars($text['title']); ?></h1>
                <div class="content-body">
                    <?php echo nl2br(htmlspecialchars($text['content'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
