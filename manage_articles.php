<?php
// File: manage_articles.php
// Purpose: Allows teachers to add, view, and manage articles.

session_start();

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
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

// Handle form submission for adding a new article
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_text'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    if (empty($title) || empty($content)) {
        $message = '<div class="message error">Title and content cannot be empty.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO articles (title, creator_id) VALUES (?, ?)");
            $stmt->execute([$title, $user_id]);
            $article_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO revisions (article_id, editor_id, content, edit_summary) VALUES (?, ?, ?, ?)");
            $stmt->execute([$article_id, $user_id, $content, 'Initial creation']);
            $pdo->commit();
            $message = '<div class="message success">Article added successfully!</div>';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch all articles
try {
    $stmt = $pdo->query("SELECT id, title, created_at FROM articles ORDER BY created_at DESC");
    $articles = $stmt->fetchAll();
} catch (PDOException $e) {
    $articles = [];
    $message .= '<div class="message error">Could not fetch articles: ' . $e->getMessage() . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Articles</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .text-list li { display: flex; justify-content: space-between; align-items: center; }
        .text-info a { font-weight: bold; }
        .text-info small { display: block; color: #6c757d; }
        .text-actions a { display: inline-block; padding: 0.35rem 0.75rem; border-radius: 4px; text-decoration: none; color: white; font-size: 0.9rem; margin-left: 0.5rem; }
        .btn-edit { background-color: #ffc107; }
        .btn-delete { background-color: #dc3545; }
        .btn-history { background-color: #17a2b8; }
        .features-menu { display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid #dee2e6; padding-bottom: 1rem; }
        .features-menu a { text-decoration: none; padding: 0.5rem 1rem; border-radius: 5px 5px 0 0; color: #495057; font-weight: 500; }
        .features-menu a.active { border-bottom: 2px solid #007bff; color: #007bff; }
        .features-menu a.disabled { color: #6c757d; cursor: not-allowed; pointer-events: none; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>! (Teacher)</span>
        <a href="teacher_dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="container">
        <h1>Manage Articles</h1>

        <div class="features-menu">
            <a href="manage_articles.php" class="active">Articoli</a>
            <a href="manage_exercises.php">Esercizi</a>
            <a href="#" class="disabled" title="Prossimamente">Obiettivi formativi</a>
            <a href="#" class="disabled" title="Prossimamente">Riscontro alunni</a>
            <a href="#" class="disabled" title="Prossimamente">Valutazioni</a>
        </div>

        <?php if (isset($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } echo $message; ?>

        <div class="form-container">
            <h2>Add New Article</h2>
            <form action="manage_articles.php" method="POST">
                <input type="hidden" name="add_text" value="1">
                <div class="form-group"><label for="title">Title</label><input type="text" id="title" name="title" required></div>
                <div class="form-group"><label for="content">Content</label><textarea id="content" name="content" required></textarea></div>
                <button type="submit" name="add_text">Add Article</button>
            </form>
        </div>

        <hr>

        <h2>Existing Articles</h2>
        <div class="text-list-container">
            <?php if (empty($articles)): ?>
                <p>No articles have been added yet.</p>
            <?php else: ?>
                <ul class="text-list">
                    <?php foreach ($articles as $article): ?>
                        <li>
                            <div class="text-info">
                                <a href="view_article.php?id=<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a>
                                <small>Added: <?php echo date('Y-m-d', strtotime($article['created_at'])); ?></small>
                            </div>
                            <div class="text-actions">
                                <a href="history.php?id=<?php echo $article['id']; ?>" class="btn-history" style="background-color: #17a2b8; color: white;">History</a>
                                <a href="edit_article.php?id=<?php echo $article['id']; ?>" class="btn-edit">Edit</a>
                                <a href="delete_article.php?id=<?php echo $article['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
