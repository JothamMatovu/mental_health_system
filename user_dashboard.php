<?php
// user_dashboard.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$login_time = isset($_SESSION['login_time']) ? date('M d, Y h:i A', $_SESSION['login_time']) : 'N/A';

// Include database connection
include '../config/config.php';

// Fetch user statistics
$user_stats = [];
$user_stats['total_entries'] = 0;
$user_stats['total_sessions'] = 0;

// Get user's journal entries count
$result = $conn->query("SELECT COUNT(*) as count FROM journal_entries WHERE user_id = $user_id");
if ($result) {
    $row = $result->fetch_assoc();
    $user_stats['total_entries'] = $row['count'];
}

// Get user's appointments count
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE user_id = $user_id");
if ($result) {
    $row = $result->fetch_assoc();
    $user_stats['total_sessions'] = $row['count'];
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Responsive Navbar -->
    <nav class="navbar">
        <h1>
            <i class="fas fa-heartbeat"></i> Mental Health System
        </h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($user_name); ?></span>
            <span class="user-role">User</span>
            <a href="../auth/logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
                <a href="../admin/admin_dashboard.php" class="btn-secondary">Admin Panel</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>Welcome Back, <?php echo htmlspecialchars($user_name); ?>!</h2>
            <p>Logged in at: <?php echo $login_time; ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $user_stats['total_sessions']; ?></h3>
                    <p>Therapy Sessions</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3>100%</h3>
                    <p>Wellness Score</p>
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

        <!-- User Actions -->
        <div class="user-grid">
            <a href="../journal/journal.php" class="user-card">
                <div class="icon"><i class="fas fa-book"></i></div>
                <h3>Journal Entries</h3>
                <p>Write and review your daily journal entries for self-reflection.</p>
            </a>

            <a href="../appointments/appointments.php" class="user-card">
                <div class="icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Appointments</h3>
                <p>Schedule and manage your therapy appointments with professionals.</p>
            </a>

            <a href="../resources/resources.php" class="user-card">
                <div class="icon"><i class="fas fa-book-open"></i></div>
                <h3>Resources</h3>
                <p>Access helpful articles, videos, and tools for mental wellness.</p>
            </a>

            <a href="../analytics/analytics.php" class="user-card">
                <div class="icon"><i class="fas fa-chart-line"></i></div>
                <h3>Progress Tracking</h3>
                <p>Track your mental health journey and see your progress over time.</p>
            </a>

            <a href="../messages/messages.php" class="user-card">
                <div class="icon"><i class="fas fa-comments"></i></div>
                <h3>Messages</h3>
                <p>Communicate securely with your therapist or support team.</p>
            </a>

            <a href="../settings/settings.php" class="user-card">
                <div class="icon"><i class="fas fa-cog"></i></div>
                <h3>Account Settings</h3>
                <p>Update your profile, password, and notification preferences.</p>
            </a>
        </div>

        <!-- Recent Activity Table -->
        <div class="table-container">
            <h3>Recent Activity</h3>
            <table>
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Journal Entry</td>
                        <td><?php echo date('M d, Y'); ?></td>
                        <td><span class="badge badge-active">Completed</span></td>
                        <td><a href="../journal/journal.php" class="action-btn btn-view">View</a></td>
                    </tr>
                    <tr>
                        <td>Therapy Session</td>
                        <td><?php echo date('M d, Y', strtotime('-7 days')); ?></td>
                        <td><span class="badge badge-active">Completed</span></td>
                        <td><a href="../appointments/appointments.php" class="action-btn btn-view">View</a></td>
                    </tr>
                    <tr>
                        <td>Resource Viewed</td>
                        <td><?php echo date('M d, Y', strtotime('-14 days')); ?></td>
                        <td><span class="badge badge-active">Completed</span></td>
                        <td><a href="../resources/resources.php" class="action-btn btn-view">View</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Mental Health System. All rights reserved.</p>
    </div>

</body>
</html>