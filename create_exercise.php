<?php
// File: create_exercise.php
// Purpose: Handles the creation of a new exercise.

session_start();

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

$message = '';
$current_theme = getCurrentTheme($pdo);

// --- Fetch articles to link ---
try {
    $stmt = $pdo->query("SELECT id, title FROM articles ORDER BY title ASC");
    $articles = $stmt->fetchAll();
} catch (PDOException $e) {
    $articles = [];
    $message = '<div class="message error">Could not fetch articles.</div>';
}


// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $linked_articles = $_POST['articles'] ?? [];
    $questions = $_POST['questions'] ?? [];
    $creator_id = $_SESSION['user_id'];

    if (empty($title) || empty($questions)) {
        $message = '<div class="message error">Title and at least one question are required.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Create the exercise
            $stmt = $pdo->prepare("INSERT INTO exercises (title, creator_id) VALUES (?, ?)");
            $stmt->execute([$title, $creator_id]);
            $exercise_id = $pdo->lastInsertId();

            // 2. Link articles
            if (!empty($linked_articles)) {
                $stmt = $pdo->prepare("INSERT INTO exercise_articles (exercise_id, article_id) VALUES (?, ?)");
                foreach ($linked_articles as $article_id) {
                    $stmt->execute([$exercise_id, $article_id]);
                }
            }

            // 3. Add questions and options
            foreach ($questions as $q_order => $question) {
                $q_text = trim($question['text']);
                $q_type = $question['type'];

                if (empty($q_text)) continue; // Skip empty questions

                $stmt = $pdo->prepare("INSERT INTO questions (exercise_id, question_text, question_type, question_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$exercise_id, $q_text, $q_type, $q_order]);
                $question_id = $pdo->lastInsertId();

                if ($q_type === 'multiple_choice' && isset($question['options'])) {
                    foreach ($question['options'] as $option) {
                        $opt_text = trim($option['text']);
                        $opt_score = (float)($option['score']);

                        if (empty($opt_text)) continue; // Skip empty options

                        $stmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, score) VALUES (?, ?, ?)");
                        $stmt->execute([$question_id, $opt_text, $opt_score]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['message'] = '<div class="message success">Exercise created successfully!</div>';
            header('Location: manage_exercises.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="message error">Failed to create exercise: ' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Exercise</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .question-block {
            border: 1px solid #ccc;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
        .option-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .option-group input[type="text"] { flex-grow: 1; }
        .remove-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.2rem 0.5rem;
            cursor: pointer;
            border-radius: 3px;
        }
        .add-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 0.3rem 0.6rem;
            cursor: pointer;
            border-radius: 3px;
            margin-top: 0.5rem;
        }
        .form-group.required label::after {
            content: ' *';
            color: red;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Create New Exercise</span>
        <a href="manage_exercises.php">Back to Exercise List</a>
    </div>

    <div class="container">
        <h1>New Exercise</h1>

        <?php echo $message; ?>

        <form action="create_exercise.php" method="POST" id="exercise-form">
            <div class="form-group required">
                <label for="title">Exercise Title</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group">
                <label for="articles">Link to Theoretical Articles (Optional)</label>
                <select id="articles" name="articles[]" multiple size="5">
                    <?php foreach ($articles as $article): ?>
                        <option value="<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <hr>

            <h2>Questions</h2>
            <div id="questions-container">
                <!-- Questions will be added here dynamically -->
            </div>

            <button type="button" id="add-question" class="add-btn">Add Question</button>
            <hr>
            <button type="submit">Create Exercise</button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let questionCounter = 0;

        document.getElementById('add-question').addEventListener('click', function() {
            const container = document.getElementById('questions-container');
            const questionId = questionCounter++;

            const questionBlock = document.createElement('div');
            questionBlock.className = 'question-block';
            questionBlock.id = `question-${questionId}`;
            questionBlock.innerHTML = `
                <button type="button" class="remove-btn" onclick="removeElement('question-${questionId}')">Remove Question</button>
                <div class="form-group required">
                    <label for="q-text-${questionId}">Question Text</label>
                    <textarea id="q-text-${questionId}" name="questions[${questionId}][text]" rows="2" required></textarea>
                </div>
                <div class="form-group">
                    <label for="q-type-${questionId}">Question Type</label>
                    <select id="q-type-${questionId}" name="questions[${questionId}][type]" onchange="toggleOptions(${questionId})">
                        <option value="multiple_choice" selected>Multiple Choice</option>
                        <option value="open_ended">Open-ended</option>
                    </select>
                </div>
                <div id="options-container-${questionId}">
                    <label>Options (provide text and score for each)</label>
                    <div class="option-group">
                         <input type="text" name="questions[${questionId}][options][0][text]" placeholder="Option text" required>
                         <input type="number" step="0.01" name="questions[${questionId}][options][0][score]" placeholder="Score" required style="width: 80px;">
                         <button type="button" class="remove-btn" onclick="this.parentElement.remove()">X</button>
                    </div>
                    <button type="button" class="add-btn" onclick="addOption(${questionId})">Add Option</button>
                </div>
            `;
            container.appendChild(questionBlock);
        });
    });

    function toggleOptions(questionId) {
        const type = document.getElementById(`q-type-${questionId}`).value;
        const optionsContainer = document.getElementById(`options-container-${questionId}`);
        const optionInputs = optionsContainer.querySelectorAll('input');

        if (type === 'open_ended') {
            optionsContainer.style.display = 'none';
            optionInputs.forEach(input => input.required = false);
        } else {
            optionsContainer.style.display = 'block';
            optionInputs.forEach(input => {
                if(input.name.includes('[text]') || input.name.includes('[score]')) {
                    input.required = true;
                }
            });
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
        // Insert before the "Add Option" button
        optionsContainer.insertBefore(optionGroup, optionsContainer.lastElementChild);
    }

    function removeElement(elementId) {
        document.getElementById(elementId).remove();
    }
    </script>
</body>
</html>
