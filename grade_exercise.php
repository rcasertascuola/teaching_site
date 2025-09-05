<?php
// File: grade_exercise.php
// Purpose: The actual grading interface for a single submission.

session_start();

// --- Security & Initialization ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$current_theme = getCurrentTheme($pdo);
$submission_id = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);
$message = '';

if (!$submission_id) {
    $_SESSION['message'] = '<div class="message error">Invalid submission ID.</div>';
    header('Location: manage_exercises.php');
    exit;
}

// --- Handle Grade Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grades'])) {
    $grades = $_POST['grades'];
    $pdo->beginTransaction();
    try {
        foreach ($grades as $answer_id => $score) {
            $stmt = $pdo->prepare("UPDATE submission_answers SET assigned_score = ? WHERE id = ?");
            // Allow null scores for ungraded open questions
            $score_value = $score === '' ? null : (float)$score;
            $stmt->execute([$score_value, (int)$answer_id]);
        }

        // Mark the entire submission as graded
        $stmt_mark = $pdo->prepare("UPDATE student_submissions SET is_graded = 1 WHERE id = ?");
        $stmt_mark->execute([$submission_id]);

        $pdo->commit();
        $message = '<div class="message success">Grades saved successfully!</div>';

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="message error">Failed to save grades: ' . $e->getMessage() . '</div>';
    }
}


// --- Fetch all data for the grading view ---
try {
    // Get submission info (exercise id, student name)
    $sql_info = "SELECT s.exercise_id, u.username FROM student_submissions s JOIN users u ON s.student_id = u.id WHERE s.id = ?";
    $stmt_info = $pdo->prepare($sql_info);
    $stmt_info->execute([$submission_id]);
    $submission_info = $stmt_info->fetch();
    if (!$submission_info) throw new Exception("Submission not found.");

    $exercise_id = $submission_info['exercise_id'];

    // Get all questions for the exercise
    $sql_q = "SELECT id, question_text, question_type, question_order FROM questions WHERE exercise_id = ? ORDER BY question_order ASC";
    $stmt_q = $pdo->prepare($sql_q);
    $stmt_q->execute([$exercise_id]);
    $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

    // For each question, get the student's answer(s) and the options
    foreach ($questions as $key => $q) {
        // Get student's answer(s)
        $sql_a = "SELECT * FROM submission_answers WHERE submission_id = ? AND question_id = ?";
        $stmt_a = $pdo->prepare($sql_a);
        $stmt_a->execute([$submission_id, $q['id']]);
        $questions[$key]['student_answers'] = $stmt_a->fetchAll(PDO::FETCH_ASSOC);

        // If multiple choice, get all options and pre-calculate auto score
        if ($q['question_type'] == 'multiple_choice') {
            $sql_o = "SELECT * FROM question_options WHERE question_id = ?";
            $stmt_o = $pdo->prepare($sql_o);
            $stmt_o->execute([$q['id']]);
            $options = $stmt_o->fetchAll(PDO::FETCH_ASSOC);
            $questions[$key]['options'] = $options;

            // Calculate auto score
            $auto_score = 0;
            $selected_option_ids = array_column($questions[$key]['student_answers'], 'selected_option_id');
            foreach($options as $option) {
                if (in_array($option['id'], $selected_option_ids)) {
                    $auto_score += $option['score'];
                }
            }
            $questions[$key]['auto_score'] = $auto_score;
        }
    }

} catch (Exception $e) {
    $_SESSION['message'] = '<div class="message error">' . $e->getMessage() . '</div>';
    header('Location: manage_exercises.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submission for <?php echo htmlspecialchars($submission_info['username']); ?></title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .question-grade { border: 1px solid #ddd; padding: 1rem; margin-bottom: 1.5rem; border-radius: 5px; }
        .student-answer { background-color: #f0f8ff; padding: 0.75rem; border-radius: 4px; margin-top: 0.5rem; }
        .option-item.correct { color: green; font-weight: bold; }
        .option-item.incorrect { color: red; }
        .option-item.selected::before { content: '▶ '; }
        .score-input { width: 100px; padding: 0.5rem; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Grading: <?php echo htmlspecialchars($submission_info['username']); ?></span>
        <a href="grade_submission.php?exercise_id=<?php echo $exercise_id; ?>">Back to Submissions</a>
    </div>

    <div class="container">
        <h1>Grade Submission</h1>
        <?php echo $message; ?>

        <form method="POST" action="grade_exercise.php?submission_id=<?php echo $submission_id; ?>">
            <?php foreach ($questions as $q): ?>
                <div class="question-grade">
                    <h4><?php echo $q['question_order']; ?>. <?php echo htmlspecialchars($q['question_text']); ?></h4>
                    <div class="student-answer">
                        <strong>Student's Answer:</strong>
                        <?php if ($q['question_type'] == 'multiple_choice'): ?>
                            <ul>
                            <?php
                            $selected_ids = array_column($q['student_answers'], 'selected_option_id');
                            foreach ($q['options'] as $opt):
                                $is_selected = in_array($opt['id'], $selected_ids);
                                $class = $is_selected ? 'selected' : '';
                                if ($is_selected) {
                                    $class .= ($opt['score'] > 0) ? ' correct' : ' incorrect';
                                }
                            ?>
                                <li class="option-item <?php echo $class; ?>">
                                    <?php echo htmlspecialchars($opt['option_text']); ?> (Score: <?php echo $opt['score']; ?>)
                                </li>
                            <?php endforeach; ?>
                            </ul>
                            <p><strong>Auto-calculated Score:</strong> <?php echo $q['auto_score']; ?></p>
                        <?php else: // open_ended ?>
                            <p><?php echo nl2br(htmlspecialchars($q['student_answers'][0]['open_ended_answer'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="grade-<?php echo $q['student_answers'][0]['id']; ?>"><strong>Assigned Score:</strong></label>
                        <input type="number" step="0.01" class="score-input"
                               name="grades[<?php echo $q['student_answers'][0]['id']; ?>]"
                               id="grade-<?php echo $q['student_answers'][0]['id']; ?>"
                               value="<?php echo $q['student_answers'][0]['assigned_score'] ?? ($q['question_type'] == 'multiple_choice' ? $q['auto_score'] : ''); ?>"
                               placeholder="Enter score">
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" name="submit_grades">Save Grades</button>
        </form>
    </div>
</body>
</html>
