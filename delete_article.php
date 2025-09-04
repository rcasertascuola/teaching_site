<?php
// File: delete_text.php
// Purpose: Handles the deletion of a text.

session_start();

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    // If not a teacher, redirect to login or a "forbidden" page.
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

// Get the article ID from the URL and validate it
$article_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$article_id || $article_id <= 0) {
    // Invalid ID, redirect back to the dashboard with an error message
    $_SESSION['message'] = '<div class="message error">Invalid article ID.</div>';
    header('Location: admin_dashboard.php');
    exit;
}

try {
    // Prepare and execute the DELETE statement for the 'articles' table.
    // The ON DELETE CASCADE constraint on the 'revisions' table will handle deleting all associated revisions.
    $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");

    if ($stmt->execute([$article_id])) {
        if ($stmt->rowCount() > 0) {
            // Deletion was successful
            $_SESSION['message'] = '<div class="message success">Article and its history deleted successfully.</div>';
        } else {
            // No rows were affected, meaning the article ID did not exist
            $_SESSION['message'] = '<div class="message error">Article not found or already deleted.</div>';
        }
    } else {
        // The query failed to execute
        $_SESSION['message'] = '<div class="message error">Failed to delete article.</div>';
    }
} catch (PDOException $e) {
    // Handle database errors
    $_SESSION['message'] = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
}

// Redirect back to the admin dashboard
header('Location: admin_dashboard.php');
exit;
?>
