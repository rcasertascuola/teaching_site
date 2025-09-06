<?php
// File: ajax_render_exercise.php
// Purpose: Renders a preview of an exercise from wikitext.

// No session needed for a simple preview, but good practice to keep it
// in case we want to check for teacher role in the future.
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

require_once 'includes/exercise_parser.php';
require_once 'includes/Parsedown.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['content'])) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$wikitext = $_POST['content'];

$parser = new ExerciseParser();
$Parsedown = new Parsedown();
$elements = $parser->parse($wikitext);

// --- Rendering Logic ---
// This could be moved to a helper class/function later to be shared with view_exercise.php

header('Content-Type: text/html');

if (empty($elements)) {
    echo "<p>No content to preview. Write some text or a question to see a preview.</p>";
    exit;
}

foreach ($elements as $element) {
    if ($element['type'] === 'content') {
        // Render markdown content to HTML
        echo '<div class="content-block">';
        echo $Parsedown->text($element['text']);
        echo '</div>';

    } elseif ($element['type'] === 'question') {
        $q = $element['data'];
        echo '<div class="question-preview">';
        echo '<p><strong>' . htmlspecialchars($q['order']) . '. ' . $Parsedown->line($q['text']) . '</strong> (' . htmlspecialchars($q['points']) . ' points)</p>';

        switch ($q['type']) {
            case 'multiple_choice':
            case 'multiple_response':
                echo '<ul style="list-style-type: none; padding-left: 20px;">';
                foreach ($q['options'] as $opt) {
                    $icon = $q['type'] === 'multiple_choice' ? '&#9675;' : '&#9744;'; // Circle or Checkbox
                    $style = $opt['is_correct'] ? 'color: green; font-weight: bold;' : '';
                    echo '<li style="' . $style . '">' . $icon . ' ' . $Parsedown->line($opt['text']) . '</li>';
                }
                echo '</ul>';
                break;

            case 'open_ended':
                echo '<textarea readonly rows="3" style="width: 90%; background-color: #f8f8f8; border: 1px dashed #ccc;">Student will write their answer here.</textarea>';
                if ($q['char_limit']) {
                    echo '<br><small>Character limit: ' . htmlspecialchars($q['char_limit']) . '</small>';
                }
                break;

            case 'cloze_test':
                 echo '<div class="cloze-preview" style="padding-left: 20px;">';
                 echo '<p><strong>Word List:</strong> ' . implode(', ', array_map('htmlspecialchars', $q['cloze_data']['word_list'])) . '</p>';
                 echo '<div>' . $Parsedown->text($q['text']) . '</div>'; // Show the text with blanks
                 echo '<p><strong>Solution:</strong></p>';
                 echo '<ul>';
                 foreach ($q['cloze_data']['solution'] as $num => $word) {
                     echo '<li>[' . htmlspecialchars($num) . ']: ' . htmlspecialchars($word) . '</li>';
                 }
                 echo '</ul>';
                 echo '</div>';
                 break;
        }
        echo '</div>'; // .question-preview
    }
    echo '<hr style="border: 0; border-top: 1px dashed #ccc;">';
}
?>
