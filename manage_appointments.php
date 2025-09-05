<?php
// File: manage_appointments.php
// Purpose: Allows teachers to manage their calendar appointments and events.

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

// Handle form submission for adding a new appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $type = $_POST['type'];

    if (empty($title) || empty($start_date) || empty($end_date)) {
        $message = '<div class="message error">Title and dates cannot be empty.</div>';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO calendar_appointments (teacher_id, title, description, start_date, end_date, type)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $title, $description, $start_date, $end_date, $type]);
            $message = '<div class="message success">Appointment added successfully!</div>';
        } catch (PDOException $e) {
            $message = '<div class="message error">Database error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Handle deletion of an appointment
if (isset($_GET['delete'])) {
    $appointment_id = (int)$_GET['delete'];
    try {
        // A teacher can only delete their own appointments
        $stmt = $pdo->prepare("DELETE FROM calendar_appointments WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$appointment_id, $user_id]);
        header('Location: manage_appointments.php');
        exit;
    } catch (PDOException $e) {
        $message = '<div class="message error">Could not delete appointment: ' . $e->getMessage() . '</div>';
    }
}

// Fetch the teacher's appointments
$stmt = $pdo->prepare("SELECT * FROM calendar_appointments WHERE teacher_id = ? ORDER BY start_date DESC");
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments</title>
    <link rel="stylesheet" href="assets/css/<?php echo $current_theme; ?>-theme.css">
    <style>
        .appointments-table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        .appointments-table th, .appointments-table td { border: 1px solid var(--border-color); padding: 0.75rem; text-align: left; }
        .appointments-table th { background-color: var(--background-color-tertiary); }
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
        <h1>Manage Calendar Appointments</h1>
        <?php echo $message; ?>

        <div class="form-container">
            <h2>Add New Appointment/Event</h2>
            <form action="manage_appointments.php" method="POST">
                <input type="hidden" name="add_appointment" value="1">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="datetime-local" id="start_date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="datetime-local" id="end_date" name="end_date" required>
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type" required>
                        <option value="appointment">Appointment</option>
                        <option value="event">Event</option>
                        <option value="holiday">Holiday</option>
                    </select>
                </div>
                <button type="submit">Add Appointment</button>
            </form>
        </div>

        <hr>

        <h2>Your Appointments</h2>
        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="5">You have no appointments.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['title']); ?></td>
                            <td><?php echo ucfirst($appointment['type']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($appointment['start_date'])); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($appointment['end_date'])); ?></td>
                            <td><a href="?delete=<?php echo $appointment['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
