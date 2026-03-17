<?php
// setup_db.php - Run this once to add missing columns
include '../config/config.php';

$messages = [];
$errors = [];

// Check and add reset_token column
$check_reset_token = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
if ($check_reset_token->num_rows === 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL")) {
        $messages[] = "✓ Added 'reset_token' column";
    } else {
        $errors[] = "✗ Failed to add 'reset_token' column: " . $conn->error;
    }
} else {
    $messages[] = "✓ 'reset_token' column already exists";
}

// Check and add reset_token_expiry column
$check_expiry = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token_expiry'");
if ($check_expiry->num_rows === 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME NULL")) {
        $messages[] = "✓ Added 'reset_token_expiry' column";
    } else {
        $errors[] = "✗ Failed to add 'reset_token_expiry' column: " . $conn->error;
    }
} else {
    $messages[] = "✓ 'reset_token_expiry' column already exists";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <link rel="stylesheet" href="../css/profile.css">
    <style>
        body { background-color: #f0f2f5; padding: 2rem; }
        .setup-container { max-width: 600px; margin: 2rem auto; background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .message { padding: 12px 15px; margin: 10px 0; border-radius: 8px; background-color: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background-color: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        h1 { color: #333; margin-bottom: 1rem; }
        a { color: #667eea; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>Database Setup</h1>
        
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message"><?php echo $msg; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="message error"><?php echo $err; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p style="margin-top: 2rem; color: #666;">
            Database columns have been set up successfully. You can now use the 
            <a href="login.php">login page</a> and 
            <a href="forgot_password.php">forgot password</a> features.
        </p>
    </div>
</body>
</html>