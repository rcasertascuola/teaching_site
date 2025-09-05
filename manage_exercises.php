<?php
// File: manage_exercises.php
// Purpose: Dashboard for managing exercises.

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
$current_theme = getCurrentTheme($pdo);

// Fetch all exercises from the database
try {
    $stmt = $pdo->query("SELECT id, title, created_at FROM exercises ORDER BY created_at DESC");
    $exercises = $stmt->fetchAll();
} catch (PDOException $e) {
    $exercises = [];
    $message = '<div class="message error">Could not fetch exercises: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exercises</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .theme-switcher { display: flex; align-items: center; }
        .theme-switcher select { margin: 0 0.5rem; padding: 0.25rem 0.5rem; }
        .navbar-right { display: flex; align-items: center; gap: 1rem; }
        .item-list li { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid #dee2e6; }
        .item-info a { font-weight: bold; text-decoration: none; color: inherit; }
        .item-info small { display: block; color: #6c757d; margin-top: 4px; }
        .item-actions a, .btn-create {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        .btn-create { background-color: #28a745; margin-bottom: 1.5rem; }
        .btn-edit { background-color: #ffc107; }
        .btn-delete { background-color: #dc3545; }
        .btn-submissions { background-color: #17a2b8; }

        .features-menu {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 1rem;
        }
        .features-menu a {
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px 5px 0 0;
            color: #495057;
            font-weight: 500;
        }
        .features-menu a.active {
            border-bottom: 2px solid #007bff;
            color: #007bff;
        }
        .features-menu a.disabled {
            color: #6c757d;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>! (Teacher)</span>
        <div class="navbar-right">
             <a href="admin_dashboard.php">Back to Dashboard</a>
             <a href="profile.php">Profile</a>
             <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>Exercise Management</h1>

        <div class="features-menu">
            <a href="admin_dashboard.php">Articoli</a>
            <a href="manage_exercises.php" class="active">Esercizi</a>
            <a href="#" class="disabled" title="Prossimamente">Obiettivi formativi</a>
            <a href="#" class="disabled" title="Prossimamente">Riscontro alunni</a>
            <a href="#" class="disabled" title="Prossimamente">Valutazioni</a>
        </div>

        <?php
        if (isset($_SESSION['message'])) {
            echo $_SESSION['message'];
            unset($_SESSION['message']);
        }
        if (isset($message)) {
            echo $message;
        }
        ?>

        <a href="create_exercise.php" class="btn-create">Create New Exercise</a>

        <div class="list-container">
            <h2>Existing Exercises</h2>
            <?php if (empty($exercises)): ?>
                <p>No exercises have been created yet.</p>
            <?php else: ?>
                <ul class="item-list">
                    <?php foreach ($exercises as $exercise): ?>
                        <li>
                            <div class="item-info">
                                <span><?php echo htmlspecialchars($exercise['title']); ?></span>
                                <small>Created: <?php echo date('Y-m-d', strtotime($exercise['created_at'])); ?></small>
                            </div>
                            <div class="item-actions">
                                <a href="grade_submission.php?exercise_id=<?php echo $exercise['id']; ?>" class="btn-submissions">View Submissions</a>
                                <a href="edit_exercise.php?id=<?php echo $exercise['id']; ?>" class="btn-edit">Edit</a>
                                <a href="delete_exercise.php?id=<?php echo $exercise['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this exercise and all its questions and submissions?');">Delete</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
