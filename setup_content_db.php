<?php
// setup_content_db.php - Add missing columns to resources table
include '../config/config.php';

$messages = [];
$errors = [];

// Check if resources table exists
$check_table = $conn->query("SHOW TABLES LIKE 'resources'");
if ($check_table->num_rows === 0) {
    // Create resources table if it doesn't exist
    $create_table = "CREATE TABLE resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(50) NOT NULL,
        content LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_table)) {
        $messages[] = "✓ Created 'resources' table";
    } else {
        $errors[] = "✗ Failed to create 'resources' table: " . $conn->error;
    }
} else {
    $messages[] = "✓ 'resources' table already exists";
    
    // Check and add content column if missing
    $check_content = $conn->query("SHOW COLUMNS FROM resources LIKE 'content'");
    if ($check_content->num_rows === 0) {
        if ($conn->query("ALTER TABLE resources ADD COLUMN content LONGTEXT")) {
            $messages[] = "✓ Added 'content' column to resources table";
        } else {
            $errors[] = "✗ Failed to add 'content' column: " . $conn->error;
        }
    } else {
        $messages[] = "✓ 'content' column already exists in resources table";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Content</title>
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
        <h1>Database Setup - Content Management</h1>
        
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
            Database setup complete! You can now use the 
            <a href="../admin/admin_dashboard.php">admin panel</a> and access 
            <a href="../content/content.php">content management</a>.
        </p>
    </div>
</body>
</html>