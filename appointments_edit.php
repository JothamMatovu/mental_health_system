<?php
// appointments_edit.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get appointment ID
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointment_id <= 0) {
    header("Location: appointments.php");
    exit();
}

// Include database connection
include '../config/config.php';

// Initialize variables
$error_message = "";
$success_message = "";

// Fetch existing appointment
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Appointment not found or doesn't belong to user
    header("Location: appointments.php");
    exit();
}

$appointment = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            // Update appointment
            $stmt = $conn->prepare("UPDATE appointments SET therapist_name = ?, appointment_date = ?, appointment_type = ?, notes = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssssii", $therapist_name, $appointment_datetime, $appointment_type, $notes, $appointment_id, $user_id);

            if ($stmt->execute()) {
                $success_message = "Appointment updated successfully!";
                // Redirect after 2 seconds
                header("Refresh: 2; url=appointments.php");
            } else {
                $error_message = "Failed to update appointment. Please try again.";
            }
            $stmt->close();
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
    <title>Edit Appointment - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-edit"></i> Edit Appointment</h1>
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
                <h2>Edit Your Appointment</h2>
                <p>Update your therapy session details</p>
            </div>

            <!-- Current Appointment Info -->
            <div class="current-info">
                <strong>Current Appointment:</strong>
                <br>
                <i class="fas fa-user-md"></i> <?php echo htmlspecialchars($appointment['therapist_name']); ?>
                <br>
                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                <br>
                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_date'])); ?>
                <br>
                <i class="fas fa-video"></i> <?php echo ucfirst($appointment['appointment_type']); ?> Session
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
                <div class="form-row">
                    <div class="form-group">
                        <label for="therapist_name">Therapist Name <span style="color: #dc3545;">*</span></label>
                        <input type="text" id="therapist_name" name="therapist_name" required 
                               value="<?php echo htmlspecialchars($appointment['therapist_name']); ?>" 
                               placeholder="Enter therapist name">
                    </div>

                    <div class="form-group">
                        <label for="appointment_type">Session Type <span style="color: #dc3545;">*</span></label>
                        <select id="appointment_type" name="appointment_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="online" <?php echo ($appointment['appointment_type'] === 'online') ? 'selected' : ''; ?>>Online Session</option>
                            <option value="in-person" <?php echo ($appointment['appointment_type'] === 'in-person') ? 'selected' : ''; ?>>In-Person Session</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="appointment_date">Date <span style="color: #dc3545;">*</span></label>
                        <input type="date" id="appointment_date" name="appointment_date" required 
                               value="<?php echo date('Y-m-d', strtotime($appointment['appointment_date'])); ?>"
                               min="<?php echo date('Y-m-d'); ?>">
                        <p class="help-text">Select a future date for your appointment</p>
                    </div>

                    <div class="form-group">
                        <label for="appointment_time">Time <span style="color: #dc3545;">*</span></label>
                        <input type="time" id="appointment_time" name="appointment_time" required 
                               value="<?php echo date('H:i', strtotime($appointment['appointment_date'])); ?>">
                        <p class="help-text">Select available time slot</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes (Optional)</label>
                    <textarea id="notes" name="notes" placeholder="Any specific concerns or information to share..."><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                    <p class="help-text">Optional: Share any relevant information about your session</p>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="appointments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>