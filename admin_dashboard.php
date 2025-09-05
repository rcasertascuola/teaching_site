<?php
// File: admin_dashboard.php
// Purpose: Redirects to the teacher's dashboard.

session_start();

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// All teacher/admin access goes to the teacher dashboard.
if ($_SESSION['user_role'] === 'teacher') {
    header('Location: teacher_dashboard.php');
    exit;
} else {
    // If the user is not a teacher, redirect to their own dashboard.
    header('Location: student_dashboard.php');
    exit;
}
