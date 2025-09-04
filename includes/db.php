<?php
// File: includes/db.php
// Purpose: Handles the connection to the MySQL database.

// --- Database Configuration ---
// Replace with your actual database credentials.
define('DB_HOST', '127.0.0.1');       // Database host (e.g., 'localhost' or '127.0.0.1')
define('DB_USERNAME', 'dottorci'); // Database username
define('DB_PASSWORD', '');   // Database password
define('DB_NAME', 'my_dottorci'); // Database name

// --- Create a Database Connection ---
try {
    // The connection options are useful for ensuring UTF-8 encoding.
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Create a new PDO instance.
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD, $options);

} catch (PDOException $e) {
    // If the connection fails, stop the script and show an error.
    // In a production environment, you would log this error instead of showing it to the user.
    die("ERROR: Could not connect to the database. " . $e->getMessage());
}

// The $pdo object can now be used by any script that includes this file.
?>
