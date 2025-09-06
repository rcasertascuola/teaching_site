<?php
// File: ajax_get_lesson.php
// Purpose: Fetches the school lesson for a specific date and time.

session_start();
header('Content-Type: application/json');

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- Includes ---
require_once 'includes/db.php';

// --- Input Validation ---
if (!isset($_GET['datetime'])) {
    echo json_encode(['error' => 'Datetime parameter is missing.']);
    exit;
}

try {
    $date = new DateTime($_GET['datetime']);
} catch (Exception $e) {
    echo json_encode(['error' => 'Invalid datetime format.']);
    exit;
}

// --- Logic ---
$user_id = $_SESSION['user_id'];
$day_of_week = $date->format('N'); // 1 for Monday, 7 for Sunday
$time = $date->format('H:i:s');

try {
    $stmt = $pdo->prepare(
        "SELECT * FROM school_schedule
         WHERE teacher_id = ?
           AND day_of_week = ?
           AND start_time <= ?
           AND end_time >= ?
         LIMIT 1"
    );
    $stmt->execute([$user_id, $day_of_week, $time, $time]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lesson) {
        // Format times for display
        $lesson['start_time_formatted'] = date('H:i', strtotime($lesson['start_time']));
        $lesson['end_time_formatted'] = date('H:i', strtotime($lesson['end_time']));
        echo json_encode(['lesson' => $lesson]);
    } else {
        echo json_encode(['lesson' => null]);
    }

} catch (PDOException $e) {
    // In a real app, log this error instead of exposing it.
    echo json_encode(['error' => 'Database query failed.']);
}
