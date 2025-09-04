<?php
// File: includes/theme_manager.php
// Purpose: Handles fetching and updating the user's theme preference.

// Ensure session is started, as we need the user_id
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to get the current theme for the user
function getCurrentTheme(PDO $pdo): string
{
    // Default theme
    $theme = 'light';

    // 1. Check if the theme is already in the session
    if (isset($_SESSION['theme'])) {
        return $_SESSION['theme'];
    }

    // 2. If not in session, check the database for a saved preference
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT theme FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $preference = $stmt->fetch();

            if ($preference && in_array($preference['theme'], ['light', 'dark'])) {
                $theme = $preference['theme'];
            }
        } catch (PDOException $e) {
            // On error, just use the default theme. Optionally log the error.
        }
    }

    // Store the theme in the session to avoid repeated database lookups
    $_SESSION['theme'] = $theme;
    return $theme;
}


// --- Handle Theme Update Request ---
// This part of the script will execute if a form is submitted to it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_theme'])) {

    // Ensure the user is logged in to change preferences
    if (isset($_SESSION['user_id'])) {
        $new_theme = $_POST['theme'];
        $user_id = $_SESSION['user_id'];

        // Validate the theme name
        if (in_array($new_theme, ['light', 'dark'])) {
            try {
                // Use an UPSERT-like query (INSERT ... ON DUPLICATE KEY UPDATE)
                // This will insert a new preference or update an existing one.
                // This relies on `user_id` having a UNIQUE constraint in `user_preferences`.
                $stmt = $pdo->prepare(
                    "INSERT INTO user_preferences (user_id, theme) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE theme = ?"
                );
                $stmt->execute([$user_id, $new_theme, $new_theme]);

                // Update the theme in the current session immediately
                $_SESSION['theme'] = $new_theme;

            } catch (PDOException $e) {
                // Handle or log the error. For now, we do nothing, and the theme won't change.
            }
        }
    }

    // Redirect back to the page the user was on
    // A hidden input in the form will provide this URL.
    $redirect_url = $_POST['redirect_url'] ?? 'student_dashboard.php'; // Default fallback
    header("Location: " . $redirect_url);
    exit;
}
?>
