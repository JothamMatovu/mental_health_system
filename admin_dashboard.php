<?php
// admin_dashboard.php
// This is the admin dashboard, hope it works
session_start();

// Check if user is logged in AND is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$uname = $_SESSION['name'];
$urole = $_SESSION['role'];
$userid = $_SESSION['user_id'];
$logintime = isset($_SESSION['login_time']) ? date('M d, Y h:i A', $_SESSION['login_time']) : 'N/A';

// Include database connection
include '../config/config.php';

// Fetch system statistics
$mystats = [];
$mystats['total_users'] = 0;
$mystats['active_users'] = 0;
$mystats['total_sessions'] = 0;

// Get total users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    $mystats['total_users'] = $row['count'];
}

// Get active users (logged in within last 24 hours)
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
if ($result) {
    $row = $result->fetch_assoc();
    $mystats['active_users'] = $row['count'];
}

// Get recent users
$recent_users = [];
$result = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}

// Get recent login attempts (for security monitoring)
$login_attempts = [];
// Note: You would need a login_attempts table for this feature
// For now, we'll show a placeholder

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Responsive Navbar -->
    <nav class="navbar">
        <h1>
            <i class="fas fa-shield-alt"></i> Admin Dashboard
        </h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($uname); ?></span>
            <span class="user-role">Administrator</span>
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
                <a href="admin_dashboard.php" class="btn-secondary">Admin Panel</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>Admin Panel - <?php echo htmlspecialchars($uname); ?></h2>
            <p>Logged in at: <?php echo $logintime; ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $mystats['total_users']; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $mystats['active_users']; ?></h3>
                    <p>Active Users (24h)</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3>100%</h3>
                    <p>System Health</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo date('H:i'); ?></h3>
                    <p>Current Time</p>
                </div>
            </div>
        </div>

        <!-- Admin Actions -->
        <div class="admin-grid">
            <a href="../users/manage_users.php" class="admin-card">
                <div class="icon"><i class="fas fa-users"></i></div>
                <h3>User Management</h3>
                <p>View, edit, and manage all registered users in the system.</p>
            </a>

            <a href="../analytics/analytics.php" class="admin-card">
                <div class="icon"><i class="fas fa-chart-bar"></i></div>
                <h3>Analytics</h3>
                <p>View system statistics and user activity reports.</p>
            </a>

            <a href="../content/content.php" class="admin-card">
                <div class="icon"><i class="fas fa-file-alt"></i></div>
                <h3>Content Management</h3>
                <p>Manage articles, resources, and system content.</p>
            </a>

            <a href="../settings/settings.php" class="admin-card">
                <div class="icon"><i class="fas fa-cog"></i></div>
                <h3>System Settings</h3>
                <p>Configure system settings and preferences.</p>
            </a>

            <a href="../security/security.php" class="admin-card">
                <div class="icon"><i class="fas fa-user-shield"></i></div>
                <h3>Security Logs</h3>
                <p>Monitor login attempts and security events.</p>
            </a>

            <a href="../backup/backup.php" class="admin-card">
                <div class="icon"><i class="fas fa-database"></i></div>
                <h3>Backup & Restore</h3>
                <p>Manage database backups and system