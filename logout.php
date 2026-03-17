<?php
// logout.php

// Start the session
session_start();

// Include database connection
require_once __DIR__ . '/../config/config.php';

// Log logout event before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['name'])) {
    $user_id = $_SESSION['user_id'];
    $name = $_SESSION['name'];
    
    $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'logout', ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $desc = "User '{$name}' logged out";
    $log_stmt->bind_param("isss", $user_id, $desc, $ip, $useragent);
    $log_stmt->execute();
    $log_stmt->close();
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear the remember me cookie if it exists
if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, '/');
}

// Redirect to login page
header("Location: login.php");
exit;
?>