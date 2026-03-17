<?php
// appointments.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_name = $_SESSION['name'];
$user_id = (int)$_SESSION['user_id'];

// Include database connection
include '../config/config.php';

// Get all appointments for this user
$appointments = [];
$stmt = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();

// Get appointment statistics
$stats = [
    'total' => count($appointments),
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($appointments as $apt) {
    if ($apt['status'] === 'scheduled') $stats['scheduled']++;
    elseif ($apt['status'] === 'completed') $stats['completed']++;
    elseif ($apt['status'] === 'cancelled') $stats['cancelled']++;
}

// Get upcoming appointments (next 7 days)
$upcoming = [];
$current_time = time();
$week_later = $current_time + (7 * 24 * 60 * 60);

foreach ($appointments as $apt) {
    $apt_time = strtotime($apt['appointment_date']);
    if ($apt_time >= $current_time && $apt_time <= $week_later && $apt['status'] === 'scheduled') {
        $upcoming[] = $apt;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-calendar-alt"></i> Appointments</h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
                <a href="../admin/admin_dashboard.php" class="btn-secondary">Admin Panel</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h2>Therapy Appointments</h2>
            <a href="appointments_add.php" class="btn-new">
                <i class="fas fa-plus"></i> Book Appointment
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card scheduled">
                <div class="icon"><i class="fas fa-calendar-check"></i></div>
                <div class="value"><?php echo $stats['scheduled']; ?></div>
                <div class="label">Scheduled</div>
            </div>
            <div class="stat-card completed">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="value"><?php echo $stats['completed']; ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-card cancelled">
                <div class="icon"><i class="fas fa-times-circle"></i></div>
                <div class="value"><?php echo $stats['cancelled']; ?></div>
                <div class="label">Cancelled</div>
            </div>
            <div class="stat-card total">
                <div class="icon"><i class="fas fa-calendar"></i></div>
                <div class="value"><?php echo $stats['total']; ?></div>
                <div class="label">Total</div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <?php if (!empty($upcoming)): ?>
            <h3 class="section-title">
                <i class="fas fa-clock"></i> Upcoming Appointments
            </h3>
            <div class="upcoming-grid">
                <?php foreach ($upcoming as $apt): ?>
                    <div class="appointment-card scheduled">
                        <div class="appointment-header">
                            <div>
                                <div class="appointment-title">
                                    <i class="fas fa-user-md"></i> <?php echo htmlspecialchars($apt['therapist_name'] ?? 'Therapy Session'); ?>
                                </div>
                                <div class="appointment-date">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?>
                                    at <?php echo date('h:i A', strtotime($apt['appointment_date'])); ?>
                                </div>
                            </div>
                            <span class="status-badge status-scheduled">
                                <i class="fas fa-circle"></i> Scheduled
                            </span>
                        </div>
                        <div class="appointment-details">
                            <div class="detail-item">
                                <i class="fas fa-video"></i>
                                <span><?php echo ucfirst($apt['appointment_type']); ?> Session</span>
                            </div>
                            <?php if (!empty($apt['notes'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-comment"></i>
                                    <span><?php echo htmlspecialchars(substr($apt['notes'], 0, 50)); ?><?php echo strlen($apt['notes']) > 50 ? '...' : ''; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="appointment-actions">
                            <a href="appointments_edit.php?id=<?php echo $apt['id']; ?>" class="btn btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="appointments_cancel.php?id=<?php echo $apt['id']; ?>" class="btn btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Upcoming Appointments</h3>
                <p>You don't have any scheduled appointments in the next 7 days.</p>
                <a href="appointments_add.php" class="btn-new">
                    <i class="fas fa-plus"></i> Book Your First Appointment
                </a>
            </div>
        <?php endif; ?>

        <!-- All Appointments Table -->
        <div class="table-container">
            <h3>All Appointments</h3