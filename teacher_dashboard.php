<?php
// File: teacher_dashboard.php
// Purpose: Dashboard for logged-in teachers.

session_start();

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

// --- Includes ---
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';
require_once 'includes/calendar.php';

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
            $stmt = $pdo->prepare("INSERT INTO articles (title, creator_id) VALUES (?, ?)");
            $stmt->execute([$title, $user_id]);
            $article_id = $pdo->lastInsertId();
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

// Fetch all articles
try {
    $stmt = $pdo->query("SELECT id, title, created_at FROM articles ORDER BY created_at DESC");
    $articles = $stmt->fetchAll();
} catch (PDOException $e) {
    $articles = [];
    $message .= '<div class="message error">Could not fetch articles: ' . $e->getMessage() . '</div>';
}

// Fetch appointments for calendar
$stmt = $pdo->prepare("SELECT * FROM calendar_appointments WHERE teacher_id = ? OR teacher_id IS NULL");
$stmt->execute([$user_id]);
$all_appointments = $stmt->fetchAll();
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

// Get current lesson
$current_day_of_week = date('N');
$current_time = date('H:i:s');
$stmt = $pdo->prepare("SELECT * FROM school_schedule WHERE teacher_id = ? AND day_of_week = ? AND start_time <= ? AND end_time >= ? LIMIT 1");
$stmt->execute([$user_id, $current_day_of_week, $current_time, $current_time]);
$current_lesson = $stmt->fetch();

// Fetch appointments for the current week
$today = new DateTime();
$start_of_week = (clone $today)->modify('monday this week');
$end_of_week = (clone $today)->modify('sunday this week');
$stmt = $pdo->prepare("SELECT * FROM calendar_appointments WHERE (teacher_id = ? OR teacher_id IS NULL) AND start_date <= ? AND end_date >= ? ORDER BY start_date");
$stmt->execute([$user_id, $end_of_week->format('Y-m-d H:i:s'), $start_of_week->format('Y-m-d H:i:s')]);
$weekly_appointments = $stmt->fetchAll();

// Calendar month/year
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
        /* General Styles */
        .theme-switcher { display: flex; align-items: center; }
        .theme-switcher select { margin: 0 0.5rem; padding: 0.25rem 0.5rem; }
        .navbar-right { display: flex; align-items: center; gap: 1rem; }

        /* Dashboard Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            align-items: start;
        }
        .main-content { grid-column: 1 / 2; }
        .sidebar { grid-column: 2 / 3; display: flex; flex-direction: column; gap: 2rem; }

        .dashboard-section {
            background-color: var(--background-color-secondary);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .dashboard-section h2 {
            margin-top: 0;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        /* Calendar */
        .calendar-container { display: flex; gap: 1rem; justify-content: space-around; }
        .calendar { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .calendar caption { font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .calendar th, .calendar td { padding: 0.5rem; text-align: center; border: 1px solid var(--border-color); }
        .calendar th { background-color: var(--background-color-tertiary); }
        .calendar td.today { background-color: #007bff; color: white; font-weight: bold; }
        .calendar .day-number { font-size: 1.1rem; }
        .calendar .appointments { font-size: 0.75rem; }
        .calendar .appointment { background-color: #ffc107; color: #333; border-radius: 3px; padding: 2px 4px; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Time and Schedule */
        .time-and-schedule { text-align: center; }
        #current-time { font-size: 2.5rem; font-weight: bold; }
        #current-date { font-size: 1.2rem; color: #6c757d; }
        .current-lesson { background-color: #28a745; color: white; padding: 1rem; border-radius: 5px; margin-top: 1rem; }
        .lesson-nav { display: flex; justify-content: space-between; margin-top: 0.5rem; }
        .lesson-nav button { background: none; border: 1px solid white; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; cursor: pointer; }

        /* Quick Actions */
        .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .quick-actions a { background-color: #007bff; color: white; text-decoration: none; padding: 1rem; border-radius: 5px; text-align: center; font-weight: bold; transition: background-color 0.3s; }
        .quick-actions a:hover { background-color: #0056b3; }

        /* Weekly Appointments */
        .weekly-appointments-list { list-style: none; padding: 0; }
        .weekly-appointments-list li { border-bottom: 1px solid var(--border-color); padding: 0.75rem 0; }
        .weekly-appointments-list li:last-child { border-bottom: none; }
        .weekly-appointments-list .date { font-weight: bold; }
        .weekly-appointments-list .type { font-size: 0.8rem; background-color: #6c757d; color: white; padding: 2px 6px; border-radius: 4px; }

        /* Article Management */
        .text-list li { display: flex; justify-content: space-between; align-items: center; }
        .text-info a { font-weight: bold; }
        .text-info small { display: block; color: #6c757d; }
        .text-actions a { display: inline-block; padding: 0.35rem 0.75rem; border-radius: 4px; text-decoration: none; color: white; font-size: 0.9rem; margin-left: 0.5rem; }
        .btn-edit { background-color: #ffc107; }
        .btn-delete { background-color: #dc3545; }
        .btn-history { background-color: #17a2b8; }
        .features-menu { display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid #dee2e6; padding-bottom: 1rem; }
        .features-menu a { text-decoration: none; padding: 0.5rem 1rem; border-radius: 5px 5px 0 0; color: #495057; font-weight: 500; }
        .features-menu a.active { border-bottom: 2px solid #007bff; color: #007bff; }
        .features-menu a.disabled { color: #6c757d; cursor: not-allowed; pointer-events: none; }
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

        <div class="dashboard-grid">
            <div class="main-content">
                <!-- Calendar Section -->
                <div class="dashboard-section">
                    <h2>Calendar</h2>
                    <div class="calendar-container">
                        <?php echo generate_calendar($current_month, $current_year, $appointments_by_date); ?>
                        <?php echo generate_calendar($next_month, $next_year, $appointments_by_date); ?>
                    </div>
                </div>

                <!-- Weekly Appointments Section -->
                <div class="dashboard-section" style="margin-top: 2rem;">
                    <h2>This Week's Appointments</h2>
                    <?php if (empty($weekly_appointments)): ?>
                        <p>No appointments scheduled for this week.</p>
                    <?php else: ?>
                        <ul class="weekly-appointments-list">
                            <?php foreach ($weekly_appointments as $appt): ?>
                                <li>
                                    <span class="date"><?php echo date('l, F jS', strtotime($appt['start_date'])); ?>:</span>
                                    <?php echo htmlspecialchars($appt['title']); ?>
                                    <span class="type <?php echo htmlspecialchars($appt['type']); ?>"><?php echo htmlspecialchars($appt['type']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar">
                <!-- Time and Schedule Section -->
                <div class="dashboard-section time-and-schedule">
                    <h2>Now</h2>
                    <div id="current-time"></div>
                    <div id="current-date"></div>
                    <div class="current-lesson" id="current-lesson-container">
                        <strong>Current Lesson:</strong>
                        <div id="lesson-details">
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
                        <div class="lesson-nav">
                            <button id="prev-hour">&lt; Hour</button>
                            <button id="prev-day">&lt; Day</button>
                            <button id="next-day">Day &gt;</button>
                            <button id="next-hour">Hour &gt;</button>
                        </div>
                    </div>
                </div>

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
                    </div>
                </div>
            </div>
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

            <?php if (isset($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } echo $message; ?>

            <div class="form-container">
                <h2>Add New Article</h2>
                <form action="teacher_dashboard.php" method="POST">
                    <input type="hidden" name="add_text" value="1">
                    <div class="form-group"><label for="title">Title</label><input type="text" id="title" name="title" required></div>
                    <div class="form-group"><label for="content">Content</label><textarea id="content" name="content" required></textarea></div>
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
                                    <a href="delete_article.php?id=<?php echo $article['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Live clock
        function updateTime() {
            const timeEl = document.getElementById('current-time');
            const dateEl = document.getElementById('current-date');
            const now = new Date();
            timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            dateEl.textContent = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Lesson Navigation
        document.addEventListener('DOMContentLoaded', () => {
            const lessonDetails = document.getElementById('lesson-details');
            let displayedDate = new Date(); // State for the displayed lesson time

            const fetchLesson = async (date) => {
                try {
                    const response = await fetch(`ajax_get_lesson.php?datetime=${date.toISOString()}`);
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    const data = await response.json();

                    if (data.error) {
                        lessonDetails.innerHTML = `<p>Error: ${data.error}</p>`;
                        return;
                    }

                    if (data.lesson) {
                        lessonDetails.innerHTML = `
                            <p>
                                <strong>${data.lesson.subject}</strong>
                                with class <strong>${data.lesson.class}</strong>
                            </p>
                            <small>
                                ${date.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' })}
                                from ${data.lesson.start_time_formatted} to ${data.lesson.end_time_formatted}
                            </small>
                        `;
                    } else {
                        lessonDetails.innerHTML = `
                            <p>No lesson scheduled at this time.</p>
                            <small>${date.toLocaleString()}</small>
                        `;
                    }
                } catch (error) {
                    lessonDetails.innerHTML = `<p>Failed to load lesson data.</p>`;
                    console.error('Fetch error:', error);
                }
            };

            document.getElementById('prev-hour').addEventListener('click', () => {
                displayedDate.setHours(displayedDate.getHours() - 1);
                fetchLesson(displayedDate);
            });
            document.getElementById('next-hour').addEventListener('click', () => {
                displayedDate.setHours(displayedDate.getHours() + 1);
                fetchLesson(displayedDate);
            });
            document.getElementById('prev-day').addEventListener('click', () => {
                displayedDate.setDate(displayedDate.getDate() - 1);
                fetchLesson(displayedDate);
            });
            document.getElementById('next-day').addEventListener('click', () => {
                displayedDate.setDate(displayedDate.getDate() + 1);
                fetchLesson(displayedDate);
            });
        });
    </script>
</body>
</html>
