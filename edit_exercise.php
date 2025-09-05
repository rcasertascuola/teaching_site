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

require_once 'includes/exercise_parser.php'; // Include the new parser

// --- Handle form submission for updating ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = $_POST['content'] ?? '';
    $linked_articles = $_POST['articles'] ?? [];

    $parser = new ExerciseParser();
    $parsed_questions = $parser->parse($content);

    if (empty($title) || empty($content)) {
        $message = '<div class="message error">Title and content cannot be empty.</div>';
    } elseif (empty($parsed_questions)) {
        $message = '<div class="message error">Content does not contain any valid questions. Please check the syntax.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Update exercise title and content
            $stmt = $pdo->prepare("UPDATE exercises SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $exercise_id]);

            // 2. Update linked articles
            $stmt = $pdo->prepare("DELETE FROM exercise_articles WHERE exercise_id = ?");
            $stmt->execute([$exercise_id]);
            if (!empty($linked_articles)) {
                $stmt_insert_article = $pdo->prepare("INSERT INTO exercise_articles (exercise_id, article_id) VALUES (?, ?)");
                foreach ($linked_articles as $article_id) {
                    $stmt_insert_article->execute([$exercise_id, $article_id]);
                }
            }

            // 3. Delete old questions and options
            $stmt = $pdo->prepare("DELETE FROM questions WHERE exercise_id = ?");
            $stmt->execute([$exercise_id]);

            // 4. Insert newly parsed questions and options
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
                             // For multi-response, distribute points or handle as needed. Here, we'll just assign full points.
                             $score = $q['points'];
                        }
                        $stmt_o->execute([$question_id, $opt['text'], $score]);
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
    $stmt = $pdo->prepare("SELECT title, content FROM exercises WHERE id = ?");
    $stmt->execute([$exercise_id]);
    $exercise = $stmt->fetch();
    if (!$exercise) {
        throw new Exception("Exercise not found.");
    }

    // Fetch linked articles
    $stmt = $pdo->prepare("SELECT article_id FROM exercise_articles WHERE exercise_id = ?");
    $stmt->execute([$exercise_id]);
    $selected_articles = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <style>
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

            <div class="form-group required">
                <label for="content">Exercise Content (Wikitext)</label>
                <textarea id="content" name="content" rows="20"><?php echo htmlspecialchars($exercise['content'] ?? ''); ?></textarea>
            </div>

            <hr>
            <button type="submit">Save Changes</button>
        </form>
    </div>

<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
    const easyMDE = new EasyMDE({element: document.getElementById('content')});
</script>
</body>
</html>
