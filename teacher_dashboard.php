<?php
// File: admin_dashboard.php
// Purpose: Dashboard for logged-in teachers (admins).

session_start();

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user_role'] !== 'teacher') {
    header('Location: student_dashboard.php');
    exit;
}

// --- Includes ---
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

// --- Logic ---
$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];
$current_theme = getCurrentTheme($pdo);

$message = '';

// Handle form submission for adding a new article
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_text'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $message = '<div class="message error">Title and content cannot be empty.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            // Step 1: Insert into articles table
            $stmt = $pdo->prepare("INSERT INTO articles (title, creator_id) VALUES (?, ?)");
            $stmt->execute([$title, $user_id]);
            $article_id = $pdo->lastInsertId();

            // Step 2: Insert the first revision
            $stmt = $pdo->prepare("INSERT INTO revisions (article_id, editor_id, content, edit_summary) VALUES (?, ?, ?, ?)");
            $stmt->execute([$article_id, $user_id, $content, 'Initial creation']);

            $pdo->commit();
            $message = '<div class="message success">Article added successfully!</div>';

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch all articles from the database
try {
    $stmt = $pdo->query("SELECT id, title, created_at FROM articles ORDER BY created_at DESC");
    $articles = $stmt->fetchAll();
} catch (PDOException $e) {
    $articles = [];
    $message .= '<div class="message error">Could not fetch articles: ' . $e->getMessage() . '</div>';
}
?>

<?php
// --- Includes ---
require_once 'includes/calendar.php';

// --- Logic ---
// ... (existing logic)

// Fetch appointments for the current teacher
$stmt = $pdo->prepare("SELECT * FROM calendar_appointments WHERE teacher_id = ? OR teacher_id IS NULL");
$stmt->execute([$user_id]);
$all_appointments = $stmt->fetchAll();

// Organize appointments by date
$appointments_by_date = [];
foreach ($all_appointments as $appointment) {
    $start_date = new DateTime($appointment['start_date']);
    $end_date = new DateTime($appointment['end_date']);
    $current_date = clone $start_date;

    while ($current_date <= $end_date) {
        $date_key = $current_date->format('Y-m-d');
        if (!isset($appointments_by_date[$date_key])) {
            $appointments_by_date[$date_key] = [];
        }
        $appointments_by_date[$date_key][] = $appointment;
        $current_date->modify('+1 day');
    }
}


// Get current month and year
$current_month = date('m');
$current_year = date('Y');
$next_month = date('m', strtotime('+1 month'));
$next_year = date('Y', strtotime('+1 month'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .theme-switcher { display: flex; align-items: center; }
        .theme-switcher select { margin: 0 0.5rem; padding: 0.25rem 0.5rem; }
        .navbar-right { display: flex; align-items: center; gap: 1rem; }
        .text-list li { display: flex; justify-content: space-between; align-items: center; }
        .text-info a { font-weight: bold; }
        .text-info small { display: block; color: #6c757d; }
        .text-actions a {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        .btn-edit { background-color: #ffc107; }
        .btn-delete { background-color: #dc3545; }
        .btn-history { background-color: #17a2b8; }

        .features-menu {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 1rem;
        }
        .features-menu a {
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px 5px 0 0;
            color: #495057;
            font-weight: 500;
        }
        .features-menu a.active {
            border-bottom: 2px solid #007bff;
            color: #007bff;
        }
        .features-menu a.disabled {
            color: #6c757d;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* New dashboard styles */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        .dashboard-section {
            background-color: var(--background-color-secondary);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .dashboard-section h2 {
            margin-top: 0;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .calendar-container {
            display: flex;
            gap: 1rem;
            justify-content: space-around;
        }
        .calendar {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .calendar caption {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .calendar th, .calendar td {
            padding: 0.5rem;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        .calendar th {
            background-color: var(--background-color-tertiary);
        }
        .calendar td.today {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .calendar .day-number {
            font-size: 1.1rem;
        }
        .calendar .appointments {
            font-size: 0.75rem;
        }
        .calendar .appointment {
            background-color: #ffc107;
            color: #333;
            border-radius: 3px;
            padding: 2px 4px;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .time-and-schedule {
            text-align: center;
        }
        #current-time {
            font-size: 2.5rem;
            font-weight: bold;
        }
        #current-date {
            font-size: 1.2rem;
            color: #6c757d;
        }
        .current-lesson {
            background-color: #28a745;
            color: white;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        .quick-actions a {
            background-color: #007bff;
            color: white;
            text-decoration: none;
            padding: 1rem;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .quick-actions a:hover {
            background-color: #0056b3;
        }

    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>! (Teacher)</span>
        <div class="navbar-right">
            <form action="includes/theme_manager.php" method="POST" class="theme-switcher">
                <input type="hidden" name="redirect_url" value="teacher_dashboard.php">
                <input type="hidden" name="change_theme" value="1">
                <select name="theme" onchange="this.form.submit()">
                    <option value="light" <?php echo ($current_theme === 'light') ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo ($current_theme === 'dark') ? 'selected' : ''; ?>>Dark</option>
                </select>
            </form>
            <a href="manage_users.php">Manage Users</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>Teacher Dashboard</h1>

<?php
// Get current lesson
$current_day_of_week = date('N'); // 1 for Monday, 7 for Sunday
$current_time = date('H:i:s');

$stmt = $pdo->prepare(
    "SELECT * FROM school_schedule
     WHERE teacher_id = ?
       AND day_of_week = ?
       AND start_time <= ?
       AND end_time >= ?
     LIMIT 1"
);
$stmt->execute([$user_id, $current_day_of_week, $current_time, $current_time]);
$current_lesson = $stmt->fetch();
?>
        <div class="dashboard-grid">

            <!-- Calendar and Time Section -->
            <div class="dashboard-section" style="grid-column: 1 / -1;">
                <h2>Dashboard</h2>
                <div class="dashboard-grid">
                    <div class="calendar-container">
                        <?php echo generate_calendar($current_month, $current_year, $appointments_by_date); ?>
                        <?php echo generate_calendar($next_month, $next_year, $appointments_by_date); ?>
                    </div>
                    <div class="time-and-schedule">
                        <div id="current-time"></div>
                        <div id="current-date"></div>
                        <div class="current-lesson" id="current-lesson-container">
                            <strong>Current Lesson:</strong>
                            <?php if ($current_lesson): ?>
                                <p>
                                    <strong><?php echo htmlspecialchars($current_lesson['subject']); ?></strong>
                                    with class <strong><?php echo htmlspecialchars($current_lesson['class']); ?></strong>
                                </p>
                                <small>Ends at: <?php echo date('H:i', strtotime($current_lesson['end_time'])); ?></small>
                            <?php else: ?>
                                <p>No lesson currently scheduled.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

<?php
// Get current lesson
$current_day_of_week = date('N'); // 1 for Monday, 7 for Sunday
$current_time = date('H:i:s');

$stmt = $pdo->prepare(
    "SELECT * FROM school_schedule
     WHERE teacher_id = ?
       AND day_of_week = ?
       AND start_time <= ?
       AND end_time >= ?
     LIMIT 1"
);
$stmt->execute([$user_id, $current_day_of_week, $current_time, $current_time]);
$current_lesson = $stmt->fetch();
?>
            <!-- Quick Actions Section -->
            <div class="dashboard-section">
                <h2>Quick Actions</h2>
                <div class="quick-actions">
                    <a href="manage_schedule.php">Manage Schedule</a>
                    <a href="manage_appointments.php">Manage Appointments</a>
                    <a href="manage_exercises.php">Manage Exercises</a>
                    <a href="#manage-articles">Manage Articles</a>
                    <a href="manage_users.php">Manage Users</a>
                    <a href="#" class="disabled">Grades</a>
                    <a href="#" class="disabled">Reports</a>
                </div>
            </div>

            <!-- Other sections can be added here -->

        </div>

        <hr style="margin: 2rem 0;">

        <!-- Article Management Section -->
        <div id="manage-articles">
            <div class="features-menu">
                <a href="#manage-articles" class="active">Articoli</a>
                <a href="manage_exercises.php">Esercizi</a>
                <a href="#" class="disabled" title="Prossimamente">Obiettivi formativi</a>
                <a href="#" class="disabled" title="Prossimamente">Riscontro alunni</a>
                <a href="#" class="disabled" title="Prossimamente">Valutazioni</a>
            </div>

            <?php
            // Display session messages if they exist
            if (isset($_SESSION['message'])) {
                echo $_SESSION['message'];
                unset($_SESSION['message']); // Clear the message after displaying it
            }
            echo $message; // Display messages from form submissions on this page
            ?>

            <div class="form-container">
                <h2>Add New Article</h2>
                <form action="teacher_dashboard.php" method="POST">
                    <input type="hidden" name="add_text" value="1">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea id="content" name="content" required></textarea>
                    </div>
                    <button type="submit" name="add_text">Add Article</button>
                </form>
            </div>

            <hr>

            <h2>Existing Articles</h2>
            <div class="text-list-container">
                <?php if (empty($articles)): ?>
                    <p>No articles have been added yet.</p>
                <?php else: ?>
                    <ul class="text-list">
                        <?php foreach ($articles as $article): ?>
                            <li>
                                <div class="text-info">
                                    <a href="view_article.php?id=<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a>
                                    <small>Added: <?php echo date('Y-m-d', strtotime($article['created_at'])); ?></small>
                                </div>
                                <div class="text-actions">
                                    <a href="history.php?id=<?php echo $article['id']; ?>" class="btn-history" style="background-color: #17a2b8; color: white;">History</a>
                                    <a href="edit_article.php?id=<?php echo $article['id']; ?>" class="btn-edit">Edit</a>
                                    <a href="delete_article.php?id=<?php echo $article['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this article?');">Delete</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateTime() {
            const timeEl = document.getElementById('current-time');
            const dateEl = document.getElementById('current-date');
            const now = new Date();

            timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            dateEl.textContent = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>
