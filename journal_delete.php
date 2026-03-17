<?php
// journal_delete.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get entry ID
$entry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($entry_id <= 0) {
    header("Location: journal.php");
    exit();
}

// Include database connection
include '../config/config.php';

// Delete entry (only if it belongs to the user)
$stmt = $conn->prepare("DELETE FROM journal_entries WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $entry_id, $user_id);

if ($stmt->execute()) {
    // Log deletion (optional - for audit trail)
    // You could create a logs table for this
}

$stmt->close();
$conn->close();

// Redirect back to journal
header("Location: journal.php?deleted=1");
exit();
?>