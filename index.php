<?php
// File: index.php
// Purpose: Main entry point of the website.

session_start();

// If the user is already logged in, redirect them to their respective dashboard.
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'teacher') {
        header('Location: teacher_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit;
}

// If no session exists, show the public landing page.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the Study Platform</title>
    <link rel="stylesheet" href="assets/css/light-theme.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .welcome-container {
            max-width: 600px;
        }
        .actions a {
            display: inline-block;
            margin: 0 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
        }
        .actions a.login {
            background-color: #007bff;
            color: white;
        }
        .actions a.register {
            background-color: #e9ecef;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <h1>Welcome to the Autonomous Study Platform</h1>
        <p>Your personal space for learning and growth. Access texts, complete exercises, and track your progress.</p>
        <div class="actions">
            <a href="login.php" class="login">Login</a>
            <a href="register.php" class="register">Register</a>
        </div>
    </div>
</body>
</html>
