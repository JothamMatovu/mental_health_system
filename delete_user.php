<?php
// delete_user.php
// Deleting users, be careful
session_start();

// ensure admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$uname = $_SESSION['name'];
include '../config/config.php';

$userid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Just in case
$temp_id = $userid;

if ($userid > 0 && $userid != $_SESSION['user_id']) {
    // Get user details for logging
    $get_user = $conn->prepare("SELECT name, email FROM users WHERE id=?");
    $get_user->bind_param("i", $userid);
    $get_user->execute();
    $result = $get_user->get_result();
    $user_info = $result->fetch_assoc();
    $get_user->close();
    
    if ($user_info) {
        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $userid);
        
        if ($stmt->execute()) {
            // Log admin action
            $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'];
            $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $desc = "Admin '" . $uname . "' deleted user: " . $user_info['name'] . " (" . $user_info['email'] . ")";
            $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
            $log_stmt->execute();
            $log_stmt->close();
            
            header("Location: manage_users.php?success=User deleted successfully");
        } else {
            header("Location: manage_users.php?error=Failed to delete user");
        }
        $stmt->close();
    } else {
        header("Location: manage_users.php?error=User not found");
    }
} else if ($userid == $_SESSION['user_id']) {
    header("Location: manage_users.php?error=Cannot delete your own account");
} else {
    header("Location: manage_users.php?error=Invalid user ID");
}

$conn->close();
?>
