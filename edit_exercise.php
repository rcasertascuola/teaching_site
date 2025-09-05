<?php
// File: edit_exercise.php
// Purpose: Handles editing an existing exercise.

session_start();

// --- Security Check & Includes ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$message = '';
$current_theme = getCurrentTheme($pdo);
$exercise_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$exercise_id) {
    $_SESSION['message'] = '<div class="message error">Invalid exercise ID.</div>';
    header('Location: manage_exercises.php');
    exit;
}

// --- Handle form submission for updating ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $linked_articles = $_POST['articles'] ?? [];
    $questions = $_POST['questions'] ?? [];

    if (empty($title) || empty($questions)) {
        $message = '<div class="message error">Title and at least one question are required.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Update exercise title
            $stmt = $pdo->prepare("UPDATE exercises SET title = ? WHERE id = ?");
            $stmt->execute([$title, $exercise_id]);

            // 2. Update linked articles (delete old, insert new)
            $stmt = $pdo->prepare("DELETE FROM exercise_articles WHERE exercise_id = ?");
            $stmt->execute([$exercise_id]);
            if (!empty($linked_articles)) {
                $stmt = $pdo->prepare("INSERT INTO exercise_articles (exercise_id, article_id) VALUES (?, ?)");
                foreach ($linked_articles as $article_id) {
                    $stmt->execute([$exercise_id, $article_id]);
                }
            }

            // 3. Update questions and options (delete old, insert new)
            // This is simpler than tracking changes, and acceptable for this use case.
            $stmt = $pdo->prepare("DELETE FROM questions WHERE exercise_id = ?");
            $stmt->execute([$exercise_id]); // CASCADE delete will handle options

            foreach ($questions as $q_order => $question) {
                $q_text = trim($question['text']);
                $q_type = $question['type'];
                if (empty($q_text)) continue;

                $stmt = $pdo->prepare("INSERT INTO questions (exercise_id, question_text, question_type, question_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$exercise_id, $q_text, $q_type, $q_order]);
                $question_id = $pdo->lastInsertId();

                if ($q_type === 'multiple_choice' && isset($question['options'])) {
                    foreach ($question['options'] as $option) {
                        $opt_text = trim($option['text']);
                        $opt_score = (float)($option['score']);
                        if (empty($opt_text) && is_null($opt_score)) continue;

                        $stmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, score) VALUES (?, ?, ?)");
                        $stmt->execute([$question_id, $opt_text, $opt_score]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['message'] = '<div class="message success">Exercise updated successfully!</div>';
            header('Location: manage_exercises.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="message error">Failed to update exercise: ' . $e->getMessage() . '</div>';
        }
    }
}


// --- Fetch existing exercise data to pre-fill the form ---
try {
    // Fetch exercise details
    $stmt = $pdo->prepare("SELECT title FROM exercises WHERE id = ?");
    $stmt->execute([$exercise_id]);
    $exercise = $stmt->fetch();
    if (!$exercise) {
        throw new Exception("Exercise not found.");
    }

    // Fetch linked articles
    $stmt = $pdo->prepare("SELECT article_id FROM exercise_articles WHERE exercise_id = ?");
    $stmt->execute([$exercise_id]);
    $selected_articles = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Fetch questions and their options
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE exercise_id = ? ORDER BY question_order ASC");
    $stmt->execute([$exercise_id]);
    $questions_data = $stmt->fetchAll();

    $stmt_opts = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ?");
    foreach($questions_data as $key => $q) {
        if ($q['question_type'] === 'multiple_choice') {
            $stmt_opts->execute([$q['id']]);
            $questions_data[$key]['options'] = $stmt_opts->fetchAll();
        }
    }

    // Fetch all articles for the select box
    $stmt = $pdo->query("SELECT id, title FROM articles ORDER BY title ASC");
    $all_articles = $stmt->fetchAll();

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
    <title>Edit Exercise</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .question-block { border: 1px solid #ccc; padding: 1rem; margin-bottom: 1rem; border-radius: 5px; }
        .option-group { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
        .option-group input[type="text"] { flex-grow: 1; }
        .remove-btn { background-color: #dc3545; color: white; border: none; padding: 0.2rem 0.5rem; cursor: pointer; border-radius: 3px; }
        .add-btn { background-color: #28a745; color: white; border: none; padding: 0.3rem 0.6rem; cursor: pointer; border-radius: 3px; margin-top: 0.5rem; }
        .form-group.required label::after { content: ' *'; color: red; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Edit Exercise</span>
        <a href="manage_exercises.php">Back to Exercise List</a>
    </div>

    <div class="container">
        <h1>Edit Exercise: <?php echo htmlspecialchars($exercise['title']); ?></h1>
        <?php echo $message; ?>

        <form action="edit_exercise.php?id=<?php echo $exercise_id; ?>" method="POST">
            <div class="form-group required">
                <label for="title">Exercise Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($exercise['title']); ?>" required>
            </div>

            <div class="form-group">
                <label for="articles">Link to Theoretical Articles (Optional)</label>
                <select id="articles" name="articles[]" multiple size="5">
                    <?php foreach ($all_articles as $article): ?>
                        <option value="<?php echo $article['id']; ?>" <?php echo in_array($article['id'], $selected_articles) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($article['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <hr>

            <h2>Questions</h2>
            <div id="questions-container">
                <!-- Existing questions will be loaded here -->
            </div>

            <button type="button" id="add-question-btn" class="add-btn">Add Question</button>
            <hr>
            <button type="submit">Save Changes</button>
        </form>
    </div>

<script>
let questionCounter = 0;
const existingQuestions = <?php echo json_encode($questions_data); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('questions-container');
    existingQuestions.forEach(q => {
        addQuestionBlock(q);
    });
});

function addQuestionBlock(questionData = null) {
    const container = document.getElementById('questions-container');
    const questionId = questionCounter++;

    const block = document.createElement('div');
    block.className = 'question-block';
    block.id = `question-${questionId}`;

    const qText = questionData ? questionData.question_text : '';
    const qType = questionData ? questionData.question_type : 'multiple_choice';

    let optionsHtml = '';
    if (qType === 'multiple_choice') {
        const options = questionData && questionData.options ? questionData.options : [{ text: '', score: '' }];
        options.forEach((opt, optIdx) => {
            optionsHtml += `
            <div class="option-group">
                <input type="text" name="questions[${questionId}][options][${optIdx}][text]" placeholder="Option text" value="${escapeHTML(opt.option_text)}" required>
                <input type="number" step="0.01" name="questions[${questionId}][options][${optIdx}][score]" placeholder="Score" value="${escapeHTML(opt.score)}" required style="width: 80px;">
                <button type="button" class="remove-btn" onclick="this.parentElement.remove()">X</button>
            </div>`;
        });
    }

    block.innerHTML = `
        <button type="button" class="remove-btn" onclick="removeElement('question-${questionId}')">Remove Question</button>
        <div class="form-group required">
            <label for="q-text-${questionId}">Question Text</label>
            <textarea id="q-text-${questionId}" name="questions[${questionId}][text]" rows="2" required>${qText}</textarea>
        </div>
        <div class="form-group">
            <label for="q-type-${questionId}">Question Type</label>
            <select id="q-type-${questionId}" name="questions[${questionId}][type]" onchange="toggleOptions(${questionId})">
                <option value="multiple_choice" ${qType === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                <option value="open_ended" ${qType === 'open_ended' ? 'selected' : ''}>Open-ended</option>
            </select>
        </div>
        <div id="options-container-${questionId}" style="display: ${qType === 'open_ended' ? 'none' : 'block'}">
            <label>Options (provide text and score for each)</label>
            ${optionsHtml}
            <button type="button" class="add-btn" onclick="addOption(${questionId})">Add Option</button>
        </div>
    `;
    container.appendChild(block);
}

document.getElementById('add-question-btn').addEventListener('click', () => addQuestionBlock());

function toggleOptions(questionId) {
    const type = document.getElementById(`q-type-${questionId}`).value;
    const optionsContainer = document.getElementById(`options-container-${questionId}`);
    const optionInputs = optionsContainer.querySelectorAll('input');

    if (type === 'open_ended') {
        optionsContainer.style.display = 'none';
        optionInputs.forEach(input => input.required = false);
    } else {
        optionsContainer.style.display = 'block';
        // Add a default option if none exist
        if (!optionsContainer.querySelector('.option-group')) {
            addOption(questionId);
        } else {
             optionsContainer.querySelectorAll('input').forEach(input => input.required = true);
        }
    }
}

function addOption(questionId) {
    const optionsContainer = document.getElementById(`options-container-${questionId}`);
    const optionId = optionsContainer.getElementsByClassName('option-group').length;

    const optionGroup = document.createElement('div');
    optionGroup.className = 'option-group';
    optionGroup.innerHTML = `
        <input type="text" name="questions[${questionId}][options][${optionId}][text]" placeholder="Option text" required>
        <input type="number" step="0.01" name="questions[${questionId}][options][${optionId}][score]" placeholder="Score" required style="width: 80px;">
        <button type="button" class="remove-btn" onclick="this.parentElement.remove()">X</button>
    `;
    optionsContainer.insertBefore(optionGroup, optionsContainer.lastElementChild);
}

function removeElement(elementId) {
    document.getElementById(elementId).remove();
}

function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    return str.toString().replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match];
    });
}

</script>
</body>
</html>
