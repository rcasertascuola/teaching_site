<?php
// File: ajax_render_article.php
// Purpose: Renders a preview of an article from wikitext for the editor.

session_start();
// Security check: only allow logged-in teachers to use this endpoint.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

require_once 'includes/wiky.inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['content'])) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$wikitext = $_POST['content'];

$wiky = new wiky();

// It's recommended by the Wiky.php author to escape HTML characters before parsing
// to prevent XSS if any raw HTML is attempted.
$safe_content = htmlspecialchars($wikitext, ENT_QUOTES, 'UTF-8');
$html = $wiky->parse($safe_content);

header('Content-Type: text/html');
echo $html;
?>
