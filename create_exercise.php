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


require_once 'includes/exercise_parser.php'; // Include the new parser

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = $_POST['content'] ?? '';
    $linked_articles = $_POST['articles'] ?? [];
    $creator_id = $_SESSION['user_id'];

    $parser = new ExerciseParser();
    $parsed_questions = $parser->parse($content);

    if (empty($title) || empty($content)) {
        $message = '<div class="message error">Title and content cannot be empty.</div>';
    } elseif (empty($parsed_questions)) {
        $message = '<div class="message error">Content does not contain any valid questions. Please check the syntax.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Create the exercise
            $stmt = $pdo->prepare("INSERT INTO exercises (title, creator_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$title, $creator_id, $content]);
            $exercise_id = $pdo->lastInsertId();

            // 2. Link articles
            if (!empty($linked_articles)) {
                $stmt_insert_article = $pdo->prepare("INSERT INTO exercise_articles (exercise_id, article_id) VALUES (?, ?)");
                foreach ($linked_articles as $article_id) {
                    $stmt_insert_article->execute([$exercise_id, $article_id]);
                }
            }

            // 3. Insert parsed questions and options
            $stmt_q = $pdo->prepare(
                "INSERT INTO questions (exercise_id, question_text, question_type, question_order, points, char_limit, cloze_data) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt_o = $pdo->prepare(
                "INSERT INTO question_options (question_id, option_text, score) VALUES (?, ?, ?)"
            );

            foreach ($parsed_questions as $q) {
                $cloze_data_json = isset($q['cloze_data']) ? json_encode($q['cloze_data']) : null;
                $stmt_q->execute([$exercise_id, $q['text'], $q['type'], $q['order'], $q['points'], $q['char_limit'] ?? null, $cloze_data_json]);
                $question_id = $pdo->lastInsertId();

                if (isset($q['options'])) {
                    foreach ($q['options'] as $opt) {
                        $score = $opt['is_correct'] ? $q['points'] : 0;
                         if ($q['type'] === 'multiple_response' && $opt['is_correct']) {
                             $score = $q['points'];
                        }
                        $stmt_o->execute([$question_id, $opt['text'], $score]);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <style>
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

            <div class="form-group required">
                <label for="content">Exercise Content (Wikitext)</label>
                <textarea id="content" name="content" rows="20"></textarea>
            </div>

            <hr>
            <button type="submit">Create Exercise</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    <script>
        const easyMDE = new EasyMDE({element: document.getElementById('content')});
    </script>
</body>
</html>
