<?php
// File: view_exercise.php
// Purpose: Allows a student to view and complete an exercise.

session_start();

// --- Security & Initialization ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$message = '';
$current_theme = getCurrentTheme($pdo);
$exercise_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$student_id = $_SESSION['user_id'];

if (!$exercise_id) {
    $_SESSION['message'] = '<div class="message error">Invalid exercise ID.</div>';
    header('Location: student_dashboard.php');
    exit;
}

// --- Check for prior submission ---
try {
    $stmt = $pdo->prepare("SELECT id FROM student_submissions WHERE exercise_id = ? AND student_id = ?");
    $stmt->execute([$exercise_id, $student_id]);
    if ($stmt->fetch()) {
        $_SESSION['message'] = '<div class="message info">You have already completed this exercise.</div>';
        header('Location: student_dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    $message = '<div class="message error">Database error checking submission status.</div>';
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];

    $pdo->beginTransaction();
    try {
        // 1. Create a submission record
        $stmt = $pdo->prepare("INSERT INTO student_submissions (exercise_id, student_id) VALUES (?, ?)");
        $stmt->execute([$exercise_id, $student_id]);
        $submission_id = $pdo->lastInsertId();

        // 2. Insert each answer
        foreach ($answers as $question_id => $answer) {
            $stmt_insert = $pdo->prepare(
                "INSERT INTO submission_answers (submission_id, question_id, selected_option_id, open_ended_answer) VALUES (?, ?, ?, ?)"
            );

            if (is_array($answer)) { // Checkbox for multiple options
                foreach($answer as $option_id) {
                    $stmt_insert->execute([$submission_id, $question_id, $option_id, null]);
                }
            } elseif (is_numeric($answer)) { // Radio button for single option
                 $stmt_insert->execute([$submission_id, $question_id, $answer, null]);
            } else { // Textarea for open-ended
                 $stmt_insert->execute([$submission_id, $question_id, null, trim($answer)]);
            }
        }

        $pdo->commit();
        $_SESSION['message'] = '<div class="message success">Exercise submitted successfully!</div>';
        header('Location: student_dashboard.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="message error">Failed to submit exercise: ' . $e->getMessage() . '</div>';
    }
}


// --- Fetch exercise data for viewing ---
try {
    $stmt = $pdo->prepare("SELECT title FROM exercises WHERE id = ?");
    $stmt->execute([$exercise_id]);
    $exercise = $stmt->fetch();
    if (!$exercise) throw new Exception("Exercise not found.");

    $stmt_q = $pdo->prepare("SELECT * FROM questions WHERE exercise_id = ? ORDER BY question_order ASC");
    $stmt_q->execute([$exercise_id]);
    $questions = $stmt_q->fetchAll();

    $stmt_o = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ?");
    $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM question_options WHERE question_id = ? AND score > 0");

    foreach ($questions as $key => $q) {
        if ($q['question_type'] === 'multiple_choice') {
            $stmt_o->execute([$q['id']]);
            $questions[$key]['options'] = $stmt_o->fetchAll();

            $stmt_c->execute([$q['id']]);
            $questions[$key]['correct_answers_count'] = $stmt_c->fetchColumn();
        }
    }
} catch (Exception $e) {
    $_SESSION['message'] = '<div class="message error">' . $e->getMessage() . '</div>';
    header('Location: student_dashboard.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Exercise: <?php echo htmlspecialchars($exercise['title']); ?></title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .question { margin-bottom: 2rem; border-bottom: 1px solid #eee; padding-bottom: 1.5rem; }
        .question p { font-weight: bold; }
        .options-list { list-style-type: none; padding-left: 0; }
        .options-list li { margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Student Area</span>
        <a href="student_dashboard.php">Back to Dashboard</a>
    </div>

    <div class="container">
        <h1><?php echo htmlspecialchars($exercise['title']); ?></h1>
        <?php echo $message; ?>

        <form action="view_exercise.php?id=<?php echo $exercise_id; ?>" method="POST">
            <?php foreach ($questions as $question): ?>
                <div class="question">
                    <p><?php echo htmlspecialchars($question['question_order']) . '. ' . htmlspecialchars($question['question_text']); ?></p>

                    <?php if ($question['question_type'] === 'multiple_choice'): ?>
                        <ul class="options-list">
                            <?php foreach ($question['options'] as $option): ?>
                                <li>
                                    <label>
                                        <?php if ($question['correct_answers_count'] > 1): ?>
                                            <input type="checkbox" name="answers[<?php echo $question['id']; ?>][]" value="<?php echo $option['id']; ?>">
                                        <?php else: ?>
                                            <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="<?php echo $option['id']; ?>" required>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($question['question_type'] === 'open_ended'): ?>
                        <textarea name="answers[<?php echo $question['id']; ?>]" rows="5" style="width: 100%;" required></textarea>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit">Submit Answers</button>
        </form>
    </div>
</body>
</html>
