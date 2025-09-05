<?php
// File: register.php
// Purpose: Handles user registration.

session_start();

require_once 'includes/db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Handle student-specific fields
    $classe = ($role === 'student') ? trim($_POST['classe']) : null;
    $anno_scolastico = ($role === 'student') ? trim($_POST['anno_scolastico']) : null;

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } elseif (!in_array($role, ['student', 'teacher'])) {
        $error_message = 'Invalid role selected.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = 'Username or email already taken.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $sql = "INSERT INTO users (username, email, password, role, classe, anno_scolastico) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);

                if ($stmt->execute([$username, $email, $hashed_password, $role, $classe, $anno_scolastico])) {
                    $_SESSION['success_message'] = 'Registration successful! Please log in.';
                    header('Location: login.php');
                    exit;
                } else {
                    $error_message = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Study Platform</title>
    <link rel="stylesheet" href="assets/css/light-theme.css">
    <style>
        /* Styles specific to login/register pages */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .form-container {
            width: 350px;
        }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; text-align: center; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Create Account</h1>

        <?php if ($error_message): ?>
            <p class="message error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="role">Register as:</label>
                <select id="role" name="role" required>
                    <option value="student" selected>Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>

            <div id="student-fields">
                <div class="form-group">
                    <label for="classe">Class (e.g., 5A)</label>
                    <input type="text" id="classe" name="classe">
                </div>
                <div class="form-group">
                    <label for="anno_scolastico">School Year (e.g., 2023/2024)</label>
                    <input type="text" id="anno_scolastico" name="anno_scolastico">
                </div>
            </div>

            <button type="submit">Register</button>
        </form>
        <p style="text-align: center; margin-top: 1rem;">
            Already have an account? <a href="login.php">Log in</a>
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const studentFields = document.getElementById('student-fields');

            function toggleStudentFields() {
                if (roleSelect.value === 'student') {
                    studentFields.style.display = 'block';
                } else {
                    studentFields.style.display = 'none';
                }
            }

            // Initial check in case the browser remembers the selection
            toggleStudentFields();

            // Add event listener for changes
            roleSelect.addEventListener('change', toggleStudentFields);
        });
    </script>
</body>
</html>
