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
$article_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$current_theme = getCurrentTheme($pdo);
$article = null; // Renamed for clarity

if (!$article_id) {
    $_SESSION['message'] = '<div class="message error">Invalid article ID.</div>';
    header('Location: admin_dashboard.php');
    exit;
}

// --- Handle form submission for updating the article ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_text'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $edit_summary = trim($_POST['edit_summary']); // New field
    $user_id = $_SESSION['user_id'];

    if (empty($title) || empty($content)) {
        $message = '<div class="message error">Title and content cannot be empty.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            // Step 1: Update the article's title
            $stmt = $pdo->prepare("UPDATE articles SET title = ? WHERE id = ?");
            $stmt->execute([$title, $article_id]);

            // Step 2: Insert a new revision
            $stmt = $pdo->prepare("INSERT INTO revisions (article_id, editor_id, content, edit_summary) VALUES (?, ?, ?, ?)");
            $stmt->execute([$article_id, $user_id, $content, $edit_summary]);

            $pdo->commit();
            $_SESSION['message'] = '<div class="message success">Article updated successfully! A new revision has been saved.</div>';
            header('Location: admin_dashboard.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- Fetch the existing article and its latest revision to pre-fill the form ---
try {
    $sql = "
        SELECT
            a.title,
            r.content
        FROM articles AS a
        JOIN revisions AS r ON a.id = r.article_id
        WHERE a.id = ?
        ORDER BY r.id DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();

    if (!$article) {
        $_SESSION['message'] = '<div class="message error">Article not found.</div>';
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
    <title>Edit Article</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
</head>
<body>
    <div class="navbar">
        <span>Edit Article</span>
        <a href="admin_dashboard.php">Back to Dashboard</a>
    </div>

    <div class="container">
        <h1>Edit Article: <?php echo htmlspecialchars($article['title']); ?></h1>

        <?php echo $message; ?>

        <div class="form-container">
            <form action="edit_article.php?id=<?php echo $article_id; ?>" method="POST">
                <input type="hidden" name="update_text" value="1">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($article['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" rows="15" required><?php echo htmlspecialchars($article['content']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_summary">Edit Summary (briefly describe your changes)</label>
                    <input type="text" id="edit_summary" name="edit_summary" placeholder="e.g., corrected spelling, added new section">
                </div>
                <button type="submit">Save Changes</button>
            </form>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    <script>
        const easyMDE = new EasyMDE({
            element: document.getElementById('content'),
            toolbar: [
                "bold", "italic", "strikethrough", "|",
                "heading-1", "heading-2", "heading-3", "|",
                "code", "quote", "unordered-list", "ordered-list", "|",
                "link", "image", "table", "horizontal-rule", "|",
                "preview", "side-by-side", "fullscreen", "|",
                "guide"
            ]
        });
    </script>
</body>
</html>
