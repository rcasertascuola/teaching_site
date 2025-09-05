<?php
// File: delete_exercise.php
// Purpose: Handles the deletion of an exercise.

session_start();

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

// Get the exercise ID from the URL and validate it
$exercise_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$exercise_id || $exercise_id <= 0) {
    // Invalid ID, redirect back to the management page with an error message
    $_SESSION['message'] = '<div class="message error">Invalid exercise ID.</div>';
    header('Location: manage_exercises.php');
    exit;
}

try {
    // The ON DELETE CASCADE constraint on the 'questions' and other related tables
    // will handle deleting all associated data (questions, options, submissions, etc.).
    $stmt = $pdo->prepare("DELETE FROM exercises WHERE id = ?");

    if ($stmt->execute([$exercise_id])) {
        if ($stmt->rowCount() > 0) {
            // Deletion was successful
            $_SESSION['message'] = '<div class="message success">Exercise and all its related data have been deleted successfully.</div>';
        } else {
            // No rows were affected, meaning the exercise ID did not exist
            $_SESSION['message'] = '<div class="message error">Exercise not found or already deleted.</div>';
        }
    } else {
        // The query failed to execute
        $_SESSION['message'] = '<div class="message error">Failed to delete exercise.</div>';
    }
} catch (PDOException $e) {
    // Handle database errors
    $_SESSION['message'] = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
}

// Redirect back to the exercise management page
header('Location: manage_exercises.php');
exit;
?>
