<?php
// File: history.php
// Purpose: Displays the revision history for a single article and handles reverting to a previous version.

session_start();

// Only teachers can perform these actions
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$article_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

// --- Handle Revert Action ---
if ($article_id && isset($_GET['revert_to'])) {
    $revert_to_id = filter_input(INPUT_GET, 'revert_to', FILTER_VALIDATE_INT);

    if ($revert_to_id) {
        $pdo->beginTransaction();
        try {
            // 1. Get the content of the revision to revert to
            $stmt = $pdo->prepare("SELECT content FROM revisions WHERE id = ? AND article_id = ?");
            $stmt->execute([$revert_to_id, $article_id]);
            $old_revision_content = $stmt->fetchColumn();

            if ($old_revision_content !== false) {
                // 2. Create a new revision with the old content
                $summary = "Reverted to revision #$revert_to_id";
                $stmt = $pdo->prepare("INSERT INTO revisions (article_id, editor_id, content, edit_summary) VALUES (?, ?, ?, ?)");
                $stmt->execute([$article_id, $user_id, $old_revision_content, $summary]);
                $pdo->commit();

                $_SESSION['message'] = '<div class="message success">Successfully reverted to a previous version.</div>';
                header("Location: view_article.php?id=$article_id");
                exit;
            } else {
                throw new Exception("Revision to revert to was not found.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = '<div class="message error">Failed to revert: ' . $e->getMessage() . '</div>';
            header("Location: history.php?id=$article_id");
            exit;
        }
    }
}

// --- Fetch data for display ---
$current_theme = getCurrentTheme($pdo);
$article_title = '';
$revisions = [];
$error_message = '';

if (!$article_id) {
    $error_message = "Invalid article ID.";
} else {
    try {
        // Fetch article title
        $stmt = $pdo->prepare("SELECT title FROM articles WHERE id = ?");
        $stmt->execute([$article_id]);
        $article_title = $stmt->fetchColumn();

        if (!$article_title) {
            $error_message = "Article not found.";
        } else {
            // Fetch all revisions
            $sql = "SELECT r.id, r.edit_summary, r.created_at, u.username AS editor_name FROM revisions AS r LEFT JOIN users AS u ON r.editor_id = u.id WHERE r.article_id = ? ORDER BY r.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$article_id]);
            $revisions = $stmt->fetchAll();
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
    <title>Revision History for <?php echo htmlspecialchars($article_title); ?></title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .revision-list { list-style: none; padding: 0; }
        .revision-list li { margin-bottom: 1rem; padding: 1rem; border: 1px solid #ddd; border-radius: 4px; }
        .revision-list li:first-child { background-color: #f8f9fa; } /* Highlight latest revision */
        .revision-meta { font-size: 0.9em; color: #6c757d; }
        .revision-summary { margin-top: 0.5rem; font-style: italic; }
        .revision-actions { margin-top: 1rem; }
        .back-link { display: inline-block; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Revision History</span>
        <a href="admin_dashboard.php">Back to Dashboard</a>
    </div>

    <div class="container">
        <a href="admin_dashboard.php" class="back-link">&larr; Back to Dashboard</a>
        <h1>History for: <?php echo htmlspecialchars($article_title); ?></h1>

        <?php
        if (isset($_SESSION['message'])) {
            echo $_SESSION['message'];
            unset($_SESSION['message']);
        }
        ?>

        <?php if ($error_message): ?>
            <p class="message error"><?php echo $error_message; ?></p>
        <?php elseif (empty($revisions)): ?>
            <p>No revision history found for this article.</p>
        <?php else: ?>
            <ul class="revision-list">
                <?php foreach ($revisions as $index => $revision): ?>
                    <li>
                        <div class="revision-meta">
                            <strong><?php echo date('F j, Y, g:i a', strtotime($revision['created_at'])); ?></strong>
                            by <?php echo htmlspecialchars($revision['editor_name'] ?? 'Unknown'); ?>
                            <?php if ($index === 0) echo '<strong>(Latest)</strong>'; ?>
                        </div>
                        <div class="revision-summary">
                            <p><?php echo htmlspecialchars($revision['edit_summary'] ?: 'No summary provided.'); ?></p>
                        </div>
                        <div class="revision-actions">
                            <?php if ($index > 0): // Don't show revert for the latest version ?>
                                <a href="history.php?id=<?php echo $article_id; ?>&revert_to=<?php echo $revision['id']; ?>"
                                   onclick="return confirm('Are you sure you want to revert to this version? This will create a new revision with this content.');">
                                   Revert to this version
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>
