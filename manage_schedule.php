<?php
// File: manage_schedule.php
// Purpose: Allows teachers to manage their weekly school schedule.

session_start();

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

// --- Includes ---
require_once 'includes/db.php';
require_once 'includes/theme_manager.php';

// --- Logic ---
$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);
$current_theme = getCurrentTheme($pdo);
$message = '';

// Handle form submission for adding/editing a schedule entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class = trim($_POST['class']);
    $day_of_week = (int)$_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $subject = trim($_POST['subject']);

    if (empty($class) || empty($subject) || $day_of_week < 1 || $day_of_week > 7) {
        $message = '<div class="message error">Please fill in all fields correctly.</div>';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO school_schedule (teacher_id, class, day_of_week, start_time, end_time, subject)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $class, $day_of_week, $start_time, $end_time, $subject]);
            $message = '<div class="message success">Schedule entry added successfully!</div>';
        } catch (PDOException $e) {
            $message = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Handle deletion of a schedule entry
if (isset($_GET['delete'])) {
    $schedule_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM school_schedule WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$schedule_id, $user_id]);
        header('Location: manage_schedule.php'); // Redirect to avoid re-deletion on refresh
        exit;
    } catch (PDOException $e) {
        $message = '<div class="message error">Could not delete entry: ' . $e->getMessage() . '</div>';
    }
}


// Fetch the teacher's current schedule
$stmt = $pdo->prepare("SELECT * FROM school_schedule WHERE teacher_id = ? ORDER BY day_of_week, start_time");
$stmt->execute([$user_id]);
$schedule = $stmt->fetchAll();

$days_of_week = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .schedule-table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        .schedule-table th, .schedule-table td { border: 1px solid var(--border-color); padding: 0.75rem; text-align: left; }
        .schedule-table th { background-color: var(--background-color-tertiary); }
        .btn-delete { color: #dc3545; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo $username; ?>! (Teacher)</span>
        <a href="teacher_dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="container">
        <h1>Manage Weekly Schedule</h1>
        <?php echo $message; ?>

        <div class="form-container">
            <h2>Add New Schedule Entry</h2>
            <form action="manage_schedule.php" method="POST">
                <div class="form-group">
                    <label for="class">Class</label>
                    <input type="text" id="class" name="class" required>
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                <div class="form-group">
                    <label for="day_of_week">Day of the Week</label>
                    <select id="day_of_week" name="day_of_week" required>
                        <?php foreach ($days_of_week as $num => $day): ?>
                            <option value="<?php echo $num; ?>"><?php echo $day; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" id="start_time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time</label>
                    <input type="time" id="end_time" name="end_time" required>
                </div>
                <button type="submit">Add Entry</button>
            </form>
        </div>

        <hr>

        <h2>Current Schedule</h2>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedule)): ?>
                    <tr><td colspan="5">Your schedule is empty.</td></tr>
                <?php else: ?>
                    <?php foreach ($schedule as $entry): ?>
                        <tr>
                            <td><?php echo $days_of_week[$entry['day_of_week']]; ?></td>
                            <td><?php echo date('H:i', strtotime($entry['start_time'])) . ' - ' . date('H:i', strtotime($entry['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($entry['class']); ?></td>
                            <td><?php echo htmlspecialchars($entry['subject']); ?></td>
                            <td><a href="?delete=<?php echo $entry['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
