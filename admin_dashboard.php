<?php
// File: admin_dashboard.php
// Purpose: Dashboard for logged-in teachers (admins).

session_start();

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user_role'] !== 'teacher') {
    header('Location: student_dashboard.php');
    exit;
}

// --- Includes ---
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

// --- Logic ---
$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];
$current_theme = getCurrentTheme($pdo);

$message = '';

// Handle form submission for adding a new text
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_text'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $message = '<div class="message error">Title and content cannot be empty.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO texts (title, content, author_id) VALUES (?, ?, ?)");
            if ($stmt->execute([$title, $content, $user_id])) {
                $message = '<div class="message success">Text added successfully!</div>';
            } else {
                $message = '<div class="message error">Failed to add text. Please try again.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch all texts from the database
try {
    $stmt = $pdo->query("SELECT id, title, created_at FROM texts ORDER BY created_at DESC");
    $texts = $stmt->fetchAll();
} catch (PDOException $e) {
    $texts = [];
    $message .= '<div class="message error">Could not fetch texts: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .theme-switcher { display: flex; align-items: center; }
        .theme-switcher select { margin: 0 0.5rem; padding: 0.25rem 0.5rem; }
        .theme-switcher button { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>! (Teacher)</span>
        <div>
            <form action="includes/theme_manager.php" method="POST" class="theme-switcher">
                <input type="hidden" name="redirect_url" value="admin_dashboard.php">
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
        <h1>Teacher Dashboard</h1>

        <?php echo $message; ?>

        <div class="form-container">
            <h2>Add New Text</h2>
            <form action="admin_dashboard.php" method="POST">
                <input type="hidden" name="add_text" value="1">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" required></textarea>
                </div>
                <button type="submit" name="add_text">Add Text</button>
            </form>
        </div>

        <hr>

        <h2>Existing Texts</h2>
        <div class="text-list-container">
            <?php if (empty($texts)): ?>
                <p>No texts have been added yet.</p>
            <?php else: ?>
                <ul class="text-list">
                    <?php foreach ($texts as $text): ?>
                        <li>
                            <span><a href="view_text.php?id=<?php echo $text['id']; ?>"><?php echo htmlspecialchars($text['title']); ?></a></span>
                            <small>Added: <?php echo date('Y-m-d', strtotime($text['created_at'])); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
