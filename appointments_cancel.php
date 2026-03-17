<?php
// appointments_cancel.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Get appointment ID
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointment_id <= 0) {
    header("Location: appointments.php");
    exit();
}

// Include database connection
include '../config/config.php';

// Cancel appointment (only if it belongs to the user)
$stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $appointment_id, $user_id);

if ($stmt->execute()) {
    // Log cancellation (optional - for audit trail)
}

$stmt->close();
$conn->close();

// Redirect back to appointments
header("Location: appointments.php?cancelled=1");
exit();
?>