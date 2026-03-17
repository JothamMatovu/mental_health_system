<?php
// profile.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'] ?? '';

// Include database connection
include '../config/config.php';

// Initialize variables
$error_message = "";
$success_message = "";
$profile_success = "";
$password_success = "";

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fetch user data from database
$stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);

        // Validation
        if (empty($name)) {
            $error_message = "Name is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if email already exists (excluding current user)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error_message = "Email already in use by another account.";
            } else {
                // Update profile
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $email, $user_id);

                if ($stmt->execute()) {
                    $profile_success = "Profile updated successfully!";
                    // Update session data
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                } else {
                    $error_message = "Failed to update profile. Please try again.";
                }
            }
            $stmt->close();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters.";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_password = $result->fetch_assoc();
            $stmt->close();

            if (!password_verify($current_password, $user_password['password'])) {
                $error_message = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);

                if ($stmt->execute()) {
                    $password_success = "Password changed successfully!";
                } else {
                    $error_message = "Failed to change password. Please try again.";
                }
            }
            $stmt->close();
        }
    }
}

// Get account statistics
$stats = [];
$stats['account_age'] = 0;
$stats['last_login'] = 'N/A';

// Calculate account age
if (!empty($user_data['created_at'])) {
    $created_date = strtotime($user_data['created_at']);
    $current_date = time();
    $days_since = floor(($current_date - $created_date) / 86400);
    $stats['account_age'] = $days_since;
}

// Get last login (you may need to implement login tracking)
// For now, we'll use created_at as a placeholder
if (!empty($user_data['created_at'])) {
    $stats['last_login'] = date('M d, Y', strtotime($user_data['created_at']));
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-user-circle"></i> Profile</h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
                <a href="admin_dashboard.php" class="btn-secondary">Admin Panel</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h2>My Profile</h2>
            <p>Manage your account information and settings</p>
        </div>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Layout -->
        <div class="profile-layout">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="profile-name"><?php echo htmlspecialchars($user_data['name']); ?></h3>
                <p class="profile-role">
                    <span class="badge" style="
                        background-color: <?php echo $user_data['role'] === 'admin' ? '#e3f2fd' : '#e8f5e9'; ?>;
                        color: <?php echo $user_data['role'] === 'admin' ? '#1976d2' : '#2e7d32'; ?>;
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 0.8rem;
                        font-weight: 600;
                    ">
                        <?php echo ucfirst($user_data['role']); ?>
                    </span>
                </p>

                <!-- Account Stats -->