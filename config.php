<?php
// config.php
// Database config, hope it's correct

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mental_health_system');

// Just in case I need these
$dbhost = DB_HOST;
$dbuser = DB_USER;

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Error reporting (disable in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
?>