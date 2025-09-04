<?php
// File: edit_text.php
// Purpose: Handles editing of an existing text.

session_start();

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$message = '';
$text_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$current_theme = getCurrentTheme($pdo);
$text = null;

if (!$text_id) {
    $_SESSION['message'] = '<div class="message error">Invalid text ID.</div>';
    header('Location: admin_dashboard.php');
    exit;
}

// --- Handle form submission for updating the text ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_text'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $message = '<div class="message error">Title and content cannot be empty.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE texts SET title = ?, content = ? WHERE id = ?");
            if ($stmt->execute([$title, $content, $text_id])) {
                $_SESSION['message'] = '<div class="message success">Text updated successfully!</div>';
                header('Location: admin_dashboard.php');
                exit;
            } else {
                $message = '<div class="message error">Failed to update text.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- Fetch the existing text to pre-fill the form ---
try {
    $stmt = $pdo->prepare("SELECT title, content FROM texts WHERE id = ?");
    $stmt->execute([$text_id]);
    $text = $stmt->fetch();

    if (!$text) {
        $_SESSION['message'] = '<div class="message error">Text not found.</div>';
        header('Location: admin_dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['message'] = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
    header('Location: admin_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Text</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
</head>
<body>
    <div class="navbar">
        <span>Edit Text</span>
        <a href="admin_dashboard.php">Back to Dashboard</a>
    </div>

    <div class="container">
        <h1>Edit Text</h1>

        <?php echo $message; ?>

        <div class="form-container">
            <form action="edit_text.php?id=<?php echo $text_id; ?>" method="POST">
                <input type="hidden" name="update_text" value="1">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($text['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" required><?php echo htmlspecialchars($text['content']); ?></textarea>
                </div>
                <button type="submit">Update Text</button>
            </form>
        </div>
    </div>
</body>
</html>
