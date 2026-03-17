<?php
// analytics.php
// Analytics / Progress Tracking
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$uname = $_SESSION['name'];
$role = $_SESSION['role'] ?? 'user';
$userId = $_SESSION['user_id'];

// Include database connection
include '../config/config.php';

// Data for Admin vs User
$stats = [];
if ($role === 'admin') {
    // Admin analytics
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

    $result = $conn->query("SELECT COUNT(*) as count FROM journal_entries");
    $stats['total_entries'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

    $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
    $stats['total_appointments'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

    $result = $conn->query("SELECT COUNT(*) as count FROM resources");
    $stats['total_resources'] = $result ? (int)$result->fetch_assoc()['count'] : 0;
} else {
    // User progress tracking
    $stmt = $conn->prepare("SELECT COUNT(*) as count, AVG(mood_rating) as average_mood FROM journal_entries WHERE user_id = ? AND mood_rating IS NOT NULL");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_entries'] = (int)$row['count'];
    $stats['average_mood'] = $row['average_mood'] ? round($row['average_mood'], 1) : 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'scheduled'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['upcoming_appointments'] = (int)$row['count'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT appointment_date, therapist_name FROM appointments WHERE user_id = ? AND status = 'scheduled' ORDER BY appointment_date ASC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['next_appointment'] = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role === 'admin' ? 'Analytics - Admin' : 'Progress Tracker'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>
    <nav class="navbar">
        <h1>
            <i class="fas fa-chart-line"></i>
            <?php echo $role === 'admin' ? 'Admin Analytics' : 'Your Progress'; ?>
        </h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($uname); ?></span>
            <span class="user-role"><?php echo ucfirst($role); ?></span>
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <?php if ($role === 'admin'): ?>
                <a href="../admin/admin_dashboard.php" class="btn-secondary">Dashboard</a>
            <?php else: ?>
                <a href="../user/user_dashboard.php" class="btn-secondary">Dashboard</a>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container">
        <div class="page-header">
            <h2><?php echo $role === 'admin' ? 'Analytics' : 'Progress Tracker'; ?></h2>
            <?php if ($role === 'admin'): ?>
                <p>System statistics and user activity reports.</p>
            <?php else: ?>
                <p>Track your mood, journal progress, and upcoming appointments.</p>
            <?php endif; ?>
        </div>

        <?php if ($role === 'admin'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_entries']; ?></h3>
                        <p>Total Journal Entries</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_appointments']; ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_resources']; ?></h3>
                        <p>Total Resources</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_entries']; ?></h3>
                        <p>Journal Entries</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-smile"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['average_mood'] ?: '0'; ?></h3>
                        <p>Average Mood</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['upcoming_appointments']; ?></h3>
                        <p>Upcoming Appointments</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo !empty($stats['next_appointment']) ? date('M d, Y', strtotime($stats['next_appointment']['appointment_date'])) : 'None'; ?></h3>
                        <p>Next Appointment</p>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3>Next Appointment Details</h3>
                <?php if (!empty($stats['next_appointment'])): ?>
                    <p><strong>When:</strong> <?php echo date('M d, Y h:i A', strtotime($stats['next_appointment']['appointment_date'])); ?></p>
                    <p><strong>With:</strong> <?php echo htmlspecialchars($stats['next_appointment']['therapist_name'] ?: 'Therapist'); ?></p>
                    <a href="../appointments/appointments.php" class="btn-primary">Manage Appointments</a>
                <?php else: ?>
                    <p>You don't have any upcoming appointments scheduled.</p>
                    <a href="../appointments/appointments.php" class="btn-primary">Schedule an Appointment</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>