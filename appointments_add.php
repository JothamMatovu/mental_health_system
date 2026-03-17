<?php
// appointments_add.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Include database connection
include '../config/config.php';

// Initialize variables
$error_message = "";
$success_message = "";

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        // Sanitize and validate inputs
        $therapist_name = trim($_POST['therapist_name']);
        $appointment_date = trim($_POST['appointment_date']);
        $appointment_time = trim($_POST['appointment_time']);
        $appointment_type = trim($_POST['appointment_type']);
        $notes = trim($_POST['notes'] ?? '');

        // Validation
        if (empty($therapist_name)) {
            $error_message = "Please enter a therapist name.";
        } elseif (empty($appointment_date)) {
            $error_message = "Please select an appointment date.";
        } elseif (empty($appointment_time)) {
            $error_message = "Please select an appointment time.";
        } elseif (empty($appointment_type)) {
            $error_message = "Please select an appointment type.";
        } else {
            // Combine date and time
            $appointment_datetime = $appointment_date . ' ' . $appointment_time;
            
            // Check if date is in the past
            if (strtotime($appointment_datetime) < time()) {
                $error_message = "Appointment date cannot be in the past.";
            } else {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO appointments (user_id, therapist_name, appointment_date, appointment_type, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $therapist_name, $appointment_datetime, $appointment_type, $notes);

                if ($stmt->execute()) {
                    $success_message = "Appointment booked successfully!";
                    // Redirect after 2 seconds
                    header("Refresh: 2; url=appointments.php");
                } else {
                    $error_message = "Failed to book appointment. Please try again.";
                }
                $stmt->close();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-calendar-plus"></i> Book Appointment</h1>
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
        <div class="form-container">
            <!-- Page Header -->
            <div class="form-header">
                <h2>Book a Therapy Appointment</h2>
                <p>Schedule your session with a therapist</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="" method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="therapist_name">Therapist Name <span style="color: #dc3545;">*</span></label>
                        <input type="text" id="therapist_name" name="therapist_name" required placeholder="Enter therapist name">
                    </div>

                    <div class="form-group">
                        <label for="appointment_type">Session Type <span style="color: #dc3545;">*</span></label>
                        <select id="appointment_type" name="appointment_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="online">Online Session</option>
                            <option value="in-person">In-Person Session</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="appointment_date">Date <span style="color: #dc3545;">*</span></label>
                        <input type="date" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                        <p class="help-text">Select a future date for your appointment</p>
                    </div>

                    <div class="form-group">
                        <label for="appointment_time">Time <span style="color: #dc3545;">*</span></label>
                        <input type="time" id="appointment_time" name="appointment_time" required>
                        <p class="help-text">Select available time slot</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes (Optional)</label>
                    <textarea id="notes" name="notes" placeholder="Any specific concerns or information to share..."></textarea>
                    <p class="help-text">Optional: Share any relevant information about your session</p>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="appointments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Book Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>