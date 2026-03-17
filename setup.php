<?php
// setup.php - Database initialization page
// This page creates all necessary tables and columns

require_once '../config/config.php';

$messages = [];
$errors = [];

// 1. Check/Create reset_token columns in users table
$result = $conn->query("DESCRIBE users");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

if (!in_array('reset_token', $columns)) {
    if ($conn->query("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL")) {
        $messages[] = "✓ Added reset_token column to users table";
    } else {
        $errors[] = "✗ Failed to add reset_token column: " . $conn->error;
    }
}

if (!in_array('reset_token_expiry', $columns)) {
    if ($conn->query("ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME DEFAULT NULL")) {
        $messages[] = "✓ Added reset_token_expiry column to users table";
    } else {
        $errors[] = "✗ Failed to add reset_token_expiry column: " . $conn->error;
    }
} else {
    $messages[] = "✓ reset_token and reset_token_expiry columns already exist";
}

// 2. Check/Create resources table
$result = $conn->query("SHOW TABLES LIKE 'resources'");
if ($result->num_rows == 0) {
    $sql = "CREATE TABLE resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        category VARCHAR(100),
        content LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_created_at (created_at)
    )";
    
    if ($conn->query($sql)) {
        $messages[] = "✓ Created resources table";
    } else {
        $errors[] = "✗ Failed to create resources table: " . $conn->error;
    }
} else {
    // Check if content column exists
    $result = $conn->query("DESCRIBE resources");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    if (!in_array('content', $columns)) {
        if ($conn->query("ALTER TABLE resources ADD COLUMN content LONGTEXT")) {
            $messages[] = "✓ Added content column to resources table";
        } else {
            $errors[] = "✗ Failed to add content column: " . $conn->error;
        }
    } else {
        $messages[] = "✓ Resources table already exists with all columns";
    }
}

// 3. Check/Create security_logs table
$result = $conn->query("SHOW TABLES LIKE 'security_logs'");
if ($result->num_rows == 0) {
    $sql = "CREATE TABLE security_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        event_type VARCHAR(50) NOT NULL,
        event_description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_event_type (event_type),
        INDEX idx_created_at (created_at),
        INDEX idx_user_id (user_id)
    )";
    
    if ($conn->query($sql)) {
        $messages[] = "✓ Created security_logs table";
    } else {
        $errors[] = "✗ Failed to create security_logs table: " . $conn->error;
    }
} else {
    $messages[] = "✓ security_logs table already exists";
}

// 4. Check/Create system_settings table
$result = $conn->query("SHOW TABLES LIKE 'system_settings'");
if ($result->num_rows == 0) {
    $sql = "CREATE TABLE system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_type ENUM('string', 'int', 'boolean', 'json') DEFAULT 'string',
        description TEXT,
        category VARCHAR(50) DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_setting_key (setting_key),
        INDEX idx_category (category)
    )";
    
    if ($conn->query($sql)) {
        $messages[] = "✓ Created system_settings table";
        
        // Insert default settings
        $default_settings = [
            ['site_name', 'Mental Health System', 'string', 'The name of the website', 'general'],
            ['site_description', 'A comprehensive mental health support platform', 'string', 'Brief description of the website', 'general'],
            ['maintenance_mode', '0', 'boolean', 'Enable maintenance mode to restrict access', 'general'],
            ['allow_registration', '1', 'boolean', 'Allow new user registrations', 'security'],
            ['require_email_verification', '0', 'boolean', 'Require email verification for new accounts', 'security'],
            ['password_min_length', '8', 'int', 'Minimum password length', 'security'],
            ['session_timeout', '30', 'int', 'Session timeout in minutes', 'security'],
            ['max_login_attempts', '5', 'int', 'Maximum failed login attempts before lockout', 'security'],
            ['lockout_duration', '15', 'int', 'Account lockout duration in minutes', 'security'],
            ['smtp_host', '', 'string', 'SMTP server hostname', 'email'],
            ['smtp_port', '587', 'int', 'SMTP server port', 'email'],
            ['smtp_username', '', 'string', 'SMTP authentication username', 'email'],
            ['smtp_password', '', 'string', 'SMTP authentication password', 'email'],
            ['smtp_encryption', 'tls', 'string', 'SMTP encryption type (tls/ssl)', 'email'],
            ['email_from_address', 'noreply@mentalhealthsystem.com', 'string', 'Default from email address', 'email'],
            ['email_from_name', 'Mental Health System', 'string', 'Default from name', 'email'],
            ['backup_frequency', 'daily', 'string', 'Automatic backup frequency (daily/weekly/monthly)', 'backup'],
            ['backup_retention', '30', 'int', 'Number of days to keep backups', 'backup'],
            ['enable_notifications', '1', 'boolean', 'Enable system notifications', 'notifications'],
            ['admin_email', '', 'string', 'Administrator email for notifications', 'notifications']
        ];
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category) VALUES (?, ?, ?, ?, ?)");
        foreach ($default_settings as $setting) {
            $stmt->bind_param("sssss", $setting[0], $setting[1], $setting[2], $setting[3], $setting[4]);
            $stmt->execute();
        }
        $stmt->close();
        $messages[] = "✓ Inserted default system settings";
    } else {
        $errors[] = "✗ Failed to create system_settings table: " . $conn->error;
    }
} else {
    $messages[] = "✓ system_settings table already exists";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .setup-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
        }
        .setup-card h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .setup-card p {
            color: #666;
            margin-bottom: 20px;
        }
        .messages {
            margin: 20px 0;
        }
        .message {
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background-color: #5568d3;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <h1>
            <i class="fas fa-database"></i> Database Setup
        </h1>
        <p>Initializing database tables and columns...</p>

        <div class="messages">
            <?php foreach ($messages as $msg): ?>
                <div class="message message-success">
                    <?php echo $msg; ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($errors as $err): ?>
                <div class="message message-error">
                    <?php echo $err; ?>
                </div>
            <?php endforeach; ?>

            <?php if (empty($messages) && empty($errors)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i> All database tables are properly configured!
                </div>
            <?php endif; ?>
        </div>

        <div class="button-group">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Go to Login
            </a>
        </div>
    </div>
</body>
</html>
