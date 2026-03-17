<?php
// journal_add.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $mood_rating = isset($_POST['mood_rating']) ? (int)$_POST['mood_rating'] : null;

    // Validation
    if (empty($content)) {
        $error_message = "Please write something in your journal.";
    } else {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO journal_entries (user_id, title, content, mood_rating) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isii", $user_id, $title, $content, $mood_rating);

        if ($stmt->execute()) {
            header("Location: journal.php?success=1");
            exit();
        } else {
            $error_message = "Failed to save entry. Please try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Journal Entry - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">