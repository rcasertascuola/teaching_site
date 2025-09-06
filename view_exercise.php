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

            if (is_array($answer)) {
                // Check if it's an associative array (cloze test) or a simple array (multiple response)
                if (array_keys($answer) !== range(0, count($answer) - 1)) {
                    // Cloze test answers, store as JSON
                    $cloze_answer_json = json_encode($answer);
                    $stmt_insert->execute([$submission_id, $question_id, null, $cloze_answer_json]);
                } else {
                    // Multiple response answers
                    foreach($answer as $option_id) {
                        $stmt_insert->execute([$submission_id, $question_id, $option_id, null]);
                    }
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

require_once 'includes/exercise_parser.php';
require_once 'includes/wiky.inc.php';

try {
    $stmt = $pdo->prepare("SELECT title, content FROM exercises WHERE id = ?");

    $stmt->execute([$exercise_id]);
    $exercise = $stmt->fetch();
    if (!$exercise) throw new Exception("Exercise not found.");


    $parser = new ExerciseParser();
    $wiky = new wiky();
    $elements = $parser->parse($exercise['content']);

    // We still need the question IDs for form submission, so we'll fetch them.
    // A mapping from question order to question ID.
    $stmt_q_ids = $pdo->prepare("SELECT id, question_order FROM questions WHERE exercise_id = ?");
    $stmt_q_ids->execute([$exercise_id]);
    $question_id_map = $stmt_q_ids->fetchAll(PDO::FETCH_KEY_PAIR);



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

        .question { margin-bottom: 2rem; border-top: 1px solid #eee; padding-top: 1.5rem; }
        .content-block { margin-bottom: 1.5rem; }

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

            <?php foreach ($elements as $element): ?>
                <?php if ($element['type'] === 'content'): ?>
                    <div class="content-block">
                        <?php echo $wiky->parse(htmlspecialchars($element['text'])); ?>
                    </div>
                <?php elseif ($element['type'] === 'question'):
                    $q = $element['data'];
                    $question_id = $question_id_map[$q['order']] ?? 0;
                    if (!$question_id) continue; // Skip if question somehow not in DB

                    $question_text_html = trim($wiky->parse(htmlspecialchars($q['text'])));
                    if (substr($question_text_html, 0, 3) === '<p>') {
                        $question_text_html = substr($question_text_html, 3, -4);
                    }
                ?>
                    <div class="question">
                        <p><strong><?php echo htmlspecialchars($q['order']) . '. ' . $question_text_html; ?></strong> (<?php echo $q['points']; ?> points)</p>
                        <?php if ($q['type'] === 'multiple_choice' || $q['type'] === 'multiple_response'): ?>
                            <ul class="options-list">
                                <?php
                                // We need option IDs for submission. We have to fetch them.
                                $stmt_opts = $pdo->prepare("SELECT id, option_text FROM question_options WHERE question_id = ?");
                                $stmt_opts->execute([$question_id]);
                                $options = $stmt_opts->fetchAll();
                                foreach ($options as $option):
                                    $option_text_html = trim($wiky->parse(htmlspecialchars($option['option_text'])));
                                     if (substr($option_text_html, 0, 3) === '<p>') {
                                        $option_text_html = substr($option_text_html, 3, -4);
                                    }
                                ?>
                                    <li>
                                        <label>
                                            <?php if ($q['type'] === 'multiple_response'): ?>
                                                <input type="checkbox" name="answers[<?php echo $question_id; ?>][]" value="<?php echo $option['id']; ?>">
                                            <?php else: ?>
                                                <input type="radio" name="answers[<?php echo $question_id; ?>]" value="<?php echo $option['id']; ?>" required>
                                            <?php endif; ?>
                                            <?php echo $option_text_html; ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif ($q['type'] === 'open_ended'): ?>
                            <textarea name="answers[<?php echo $question_id; ?>]" rows="5" style="width: 100%;"
                                      <?php if ($q['char_limit']) echo 'maxlength="' . $q['char_limit'] . '"'; ?>
                                      required></textarea>
                            <?php if ($q['char_limit']): ?>
                                <small>Max characters: <?php echo $q['char_limit']; ?></small>
                            <?php endif; ?>

                        <?php elseif ($q['type'] === 'cloze_test'): ?>
                            <div class="cloze-text">
                                <?php echo $wiky->parse(htmlspecialchars($q['text'])); ?>
                            </div>
                            <div class="cloze-word-list" style="margin-top: 1rem;">
                                <strong>Word List:</strong> <?php echo implode(', ', array_map('htmlspecialchars', $q['cloze_data']['word_list'])); ?>
                            </div>
                            <div class="cloze-inputs" style="margin-top: 1rem;">
                                <?php
                                $num_blanks = count($q['cloze_data']['solution']);
                                for ($i = 1; $i <= $num_blanks; $i++): ?>
                                    <div class="form-group">
                                        <label for="cloze_<?php echo $question_id; ?>_<?php echo $i; ?>">Blank [<?php echo $i; ?>]:</label>
                                        <input type="text" id="cloze_<?php echo $question_id; ?>_<?php echo $i; ?>" name="answers[<?php echo $question_id; ?>][<?php echo $i; ?>]" required>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endforeach; ?>

            <button type="submit">Submit Answers</button>
        </form>
    </div>
</body>
</html>
