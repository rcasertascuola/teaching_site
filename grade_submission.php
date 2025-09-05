<?php
// File: grade_submission.php
// Purpose: Allows a teacher to view and grade student submissions for an exercise.

session_start();

// --- Security & Initialization ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$current_theme = getCurrentTheme($pdo);
$exercise_id = filter_input(INPUT_GET, 'exercise_id', FILTER_VALIDATE_INT);

if (!$exercise_id) {
    $_SESSION['message'] = '<div class="message error">Invalid exercise ID.</div>';
    header('Location: manage_exercises.php');
    exit;
}

// --- Fetch exercise title ---
try {
    $stmt = $pdo->prepare("SELECT title FROM exercises WHERE id = ?");
    $stmt->execute([$exercise_id]);
    $exercise = $stmt->fetch();
    if (!$exercise) {
        throw new Exception("Exercise not found.");
    }
} catch (Exception $e) {
    $_SESSION['message'] = '<div class="message error">' . $e->getMessage() . '</div>';
    header('Location: manage_exercises.php');
    exit;
}

// --- Fetch submissions for this exercise ---
try {
    $sql = "
        SELECT
            ss.id AS submission_id,
            ss.submitted_at,
            ss.is_graded,
            u.username,
            u.id AS student_id,
            (SELECT SUM(sa.assigned_score) FROM submission_answers sa WHERE sa.submission_id = ss.id) AS total_score
        FROM student_submissions ss
        JOIN users u ON ss.student_id = u.id
        WHERE ss.exercise_id = ?
        ORDER BY ss.submitted_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exercise_id]);
    $submissions = $stmt->fetchAll();
} catch (PDOException $e) {
    $submissions = [];
    $message = '<div class="message error">Could not fetch submissions: ' . $e->getMessage() . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions for <?php echo htmlspecialchars($exercise['title']); ?></title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .submission-list { list-style-type: none; padding: 0; }
        .submission-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid #ccc; }
        .submission-info { flex-grow: 1; }
        .grade-status { font-style: italic; color: #6c757d; }
        .grade-status.graded { color: #28a745; }
        .btn-grade {
            text-decoration: none;
            padding: 0.4rem 0.8rem;
            color: white;
            background-color: #007bff;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Grading Area</span>
        <a href="manage_exercises.php">Back to Exercise List</a>
    </div>

    <div class="container">
        <h1>Submissions for: <?php echo htmlspecialchars($exercise['title']); ?></h1>

        <?php if (isset($message)) echo $message; ?>

        <?php if (empty($submissions)): ?>
            <p>No submissions have been made for this exercise yet.</p>
        <?php else: ?>
            <ul class="submission-list">
                <?php foreach ($submissions as $sub): ?>
                    <li class="submission-item">
                        <div class="submission-info">
                            <strong><?php echo htmlspecialchars($sub['username']); ?></strong>
                            <small>(Submitted: <?php echo date('Y-m-d H:i', strtotime($sub['submitted_at'])); ?>)</small>
                            <br>
                            <span class="grade-status <?php echo $sub['is_graded'] ? 'graded' : ''; ?>">
                                <?php echo $sub['is_graded'] ? 'Graded' : 'Not Graded'; ?>
                            </span>
                            <?php if ($sub['is_graded']): ?>
                                <span> - Score: <?php echo number_format($sub['total_score'], 2); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="submission-actions">
                            <a href="grade_exercise.php?submission_id=<?php echo $sub['submission_id']; ?>" class="btn-grade">
                                <?php echo $sub['is_graded'] ? 'View/Edit Grade' : 'Grade Now'; ?>
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>
