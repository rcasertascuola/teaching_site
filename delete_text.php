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

// Get the text ID from the URL and validate it
$text_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$text_id || $text_id <= 0) {
    // Invalid ID, redirect back to the dashboard with an error message
    $_SESSION['message'] = '<div class="message error">Invalid text ID.</div>';
    header('Location: admin_dashboard.php');
    exit;
}

try {
    // Prepare and execute the DELETE statement
    $stmt = $pdo->prepare("DELETE FROM texts WHERE id = ?");

    // We can also check if the text belongs to the user, but for now any teacher can delete any text.
    if ($stmt->execute([$text_id])) {
        if ($stmt->rowCount() > 0) {
            // Deletion was successful
            $_SESSION['message'] = '<div class="message success">Text deleted successfully.</div>';
        } else {
            // No rows were affected, meaning the text ID did not exist
            $_SESSION['message'] = '<div class="message error">Text not found or already deleted.</div>';
        }
    } else {
        // The query failed to execute
        $_SESSION['message'] = '<div class="message error">Failed to delete text.</div>';
    }
} catch (PDOException $e) {
    // Handle database errors
    $_SESSION['message'] = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
}

// Redirect back to the admin dashboard
header('Location: admin_dashboard.php');
exit;
?>
