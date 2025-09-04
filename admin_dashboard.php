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
        .navbar-right { display: flex; align-items: center; gap: 1rem; }
        .text-list li { display: flex; justify-content: space-between; align-items: center; }
        .text-info a { font-weight: bold; }
        .text-info small { display: block; color: #6c757d; }
        .text-actions a {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        .btn-edit { background-color: #ffc107; }
        .btn-delete { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>! (Teacher)</span>
        <div class="navbar-right">
            <form action="includes/theme_manager.php" method="POST" class="theme-switcher">
                <input type="hidden" name="redirect_url" value="admin_dashboard.php">
                <input type="hidden" name="change_theme" value="1">
                <select name="theme" onchange="this.form.submit()">
                    <option value="light" <?php echo ($current_theme === 'light') ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo ($current_theme === 'dark') ? 'selected' : ''; ?>>Dark</option>
                </select>
            </form>
            <a href="manage_users.php">Manage Users</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>Teacher Dashboard</h1>

        <?php
        // Display session messages if they exist
        if (isset($_SESSION['message'])) {
            echo $_SESSION['message'];
            unset($_SESSION['message']); // Clear the message after displaying it
        }
        echo $message; // Display messages from form submissions on this page
        ?>

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
                            <div class="text-info">
                                <a href="view_text.php?id=<?php echo $text['id']; ?>"><?php echo htmlspecialchars($text['title']); ?></a>
                                <small>Added: <?php echo date('Y-m-d', strtotime($text['created_at'])); ?></small>
                            </div>
                            <div class="text-actions">
                                <a href="edit_text.php?id=<?php echo $text['id']; ?>" class="btn-edit">Edit</a>
                                <a href="delete_text.php?id=<?php echo $text['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this text?');">Delete</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
