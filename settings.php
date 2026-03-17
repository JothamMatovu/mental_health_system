<?php
// settings.php - User Settings and System Settings (Admin)
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'] ?? '';
$user_role = $_SESSION['role'] ?? 'user';
$is_admin = ($user_role === 'admin');

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

// Handle system settings update (Admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_system_settings'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $settings_to_update = [
            'site_name', 'site_description', 'maintenance_mode', 'allow_registration',
            'require_email_verification', 'password_min_length', 'session_timeout',
            'max_login_attempts', 'lockout_duration', 'smtp_host', 'smtp_port',
            'smtp_username', 'smtp_encryption', 'email_from_address', 'email_from_name',
            'backup_frequency', 'backup_retention', 'enable_notifications', 'admin_email'
        ];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            foreach ($settings_to_update as $key) {
                if (isset($_POST[$key])) {
                    $value = trim($_POST[$key]);
                    // Convert checkboxes to 0/1
                    if (in_array($key, ['maintenance_mode', 'allow_registration', 'require_email_verification', 'enable_notifications'])) {
                        $value = isset($_POST[$key]) ? '1' : '0';
                    }
                    $stmt->bind_param("ss", $value, $key);
                    $stmt->execute();
                }
            }
            $stmt->close();
            $conn->commit();
            $success_message = "System settings updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Failed to update system settings: " . $e->getMessage();
        }
    }
}

// Handle user settings update
if (!$is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_settings'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        // Get settings
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $theme = isset($_POST['theme']) ? trim($_POST['theme']) : 'light';
        $language = isset($_POST['language']) ? trim($_POST['language']) : 'en';
        $privacy = isset($_POST['privacy']) ? trim($_POST['privacy']) : 'private';
        $session_timeout = isset($_POST['session_timeout']) ? (int)$_POST['session_timeout'] : 30;

        // Validation
        if (!in_array($theme, ['light', 'dark', 'auto'])) {
            $error_message = "Invalid theme selection.";
        } elseif (!in_array($language, ['en', 'es', 'fr', 'de'])) {
            $error_message = "Invalid language selection.";
        } elseif (!in_array($privacy, ['public', 'private', 'friends'])) {
            $error_message = "Invalid privacy setting.";
        } elseif ($session_timeout < 15 || $session_timeout > 120) {
            $error_message = "Session timeout must be between 15 and 120 minutes.";
        } else {
            // Update settings (you may want to create a settings table)
            // For now, we'll store in session and show success message
            $_SESSION['email_notifications'] = $email_notifications;
            $_SESSION['sms_notifications'] = $sms_notifications;
            $_SESSION['push_notifications'] = $push_notifications;
            $_SESSION['theme'] = $theme;
            $_SESSION['language'] = $language;
            $_SESSION['privacy'] = $privacy;
            $_SESSION['session_timeout'] = $session_timeout;

            $success_message = "Settings updated successfully!";
        }
    }
}

// Handle delete account (User only)
if (!$is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $confirm_password = $_POST['confirm_password'];

        // Verify password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_password = $result->fetch_assoc();
        $stmt->close();

        if (!password_verify($confirm_password, $user_password['password'])) {
            $error_message = "Password is incorrect. Account deletion failed.";
        } else {
            // Delete user and related data (cascade)
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                session_destroy();
                header("Location: ../auth/login.php?deleted=1");
                exit();
            } else {
                $error_message = "Failed to delete account. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Load system settings (Admin only)
$system_settings = [];
if ($is_admin) {
    $result = $conn->query("SELECT setting_key, setting_value, setting_type, description, category FROM system_settings ORDER BY category, setting_key");
    while ($row = $result->fetch_assoc()) {
        $system_settings[$row['setting_key']] = $row;
    }
}

// Fetch user data from database (User only)
$user_data = null;
if (!$is_admin) {
    $stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
}

// Get user statistics (User only)
$stats = [];
if (!$is_admin && $user_data) {
    $stats['account_age'] = 0;
    $stats['total_entries'] = 0;
    $stats['total_appointments'] = 0;

    // Calculate account age
    if (!empty($user_data['created_at'])) {
        $created_date = strtotime($user_data['created_at']);
        $current_date = time();
        $days_since = floor(($current_date - $created_date) / 86400);
        $stats['account_age'] = $days_since;
    }

    // Get journal entries count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM journal_entries WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_entries'] = $row['count'];
    $stmt->close();

    // Get appointments count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_appointments'] = $row['count'];
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_admin ? 'System Settings' : 'Settings'; ?> - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
    <style>
        .settings-tabs { display: flex; margin-bottom: 2rem; border-bottom: 1px solid #e0e0e0; }
        .tab-button { padding: 12px 24px; border: none; background: none; cursor: pointer; font-size: 1rem; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .tab-button.active { border-bottom-color: #667eea; color: #667eea; font-weight: 600; }
        .tab-button:hover { background-color: #f8f9fa; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .settings-section { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .settings-section h3 { color: #333; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #667eea; }
        .setting-group { margin-bottom: 2rem; }
        .setting-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        .setting-group input[type="text"], .setting-group input[type="email"], .setting-group input[type="number"], .setting-group select, .setting-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .setting-group textarea { resize: vertical; min-height: 80px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .danger-zone { border: 2px solid #dc3545; background-color: #fff5f5; }
        .danger-zone h3 { color: #dc3545; border-bottom-color: #dc3545; }
        .btn-danger { background-color: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn-danger:hover { background-color: #c82333; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: center; }
        .stat-card h4 { color: #666; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: #333; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-cog"></i> <?php echo $is_admin ? 'System Settings' : 'Settings'; ?></h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($user_name); ?></span>
            <span class="user-role"><?php echo ucfirst($user_role); ?></span>
            <a href="<?php echo $is_admin ? '../admin/admin_dashboard.php' : '../user_dashboard.php'; ?>" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Messages -->
        <?php if (!empty($error_message)): ?>
            <div class="message error" style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message success" style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <!-- System Settings for Admin -->
            <div class="settings-tabs">
                <button class="tab-button active" onclick="showTab('general')">General</button>
                <button class="tab-button" onclick="showTab('security')">Security</button>
                <button class="tab-button" onclick="showTab('email')">Email</button>
                <button class="tab-button" onclick="showTab('backup')">Backup</button>
                <button class="tab-button" onclick="showTab('notifications')">Notifications</button>
            </div>

            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- General Settings -->
                <div id="general" class="tab-content active">
                    <div class="settings-section">
                        <h3><i class="fas fa-globe"></i> General Settings</h3>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="site_name">Site Name</label>
                                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($system_settings['site_name']['setting_value'] ?? ''); ?>" required>
                            </div>
                            <div class="setting-group">
                                <label for="site_description">Site Description</label>
                                <input type="text" id="site_description" name="site_description" value="<?php echo htmlspecialchars($system_settings['site_description']['setting_value'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="setting-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo ($system_settings['maintenance_mode']['setting_value'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <label for="maintenance_mode">Enable Maintenance Mode</label>
                            </div>
                            <small style="color: #666; display: block; margin-top: 5px;">When enabled, only administrators can access the site.</small>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div id="security" class="tab-content">
                    <div class="settings-section">
                        <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                        <div class="form-row">
                            <div class="setting-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="allow_registration" name="allow_registration" value="1" <?php echo ($system_settings['allow_registration']['setting_value'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <label for="allow_registration">Allow New User Registrations</label>
                                </div>
                            </div>
                            <div class="setting-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="require_email_verification" name="require_email_verification" value="1" <?php echo ($system_settings['require_email_verification']['setting_value'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <label for="require_email_verification">Require Email Verification</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="password_min_length">Minimum Password Length</label>
                                <input type="number" id="password_min_length" name="password_min_length" min="6" max="50" value="<?php echo htmlspecialchars($system_settings['password_min_length']['setting_value'] ?? '8'); ?>">
                            </div>
                            <div class="setting-group">
                                <label for="session_timeout">Session Timeout (minutes)</label>
                                <input type="number" id="session_timeout" name="session_timeout" min="15" max="480" value="<?php echo htmlspecialchars($system_settings['session_timeout']['setting_value'] ?? '30'); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="max_login_attempts">Max Login Attempts</label>
                                <input type="number" id="max_login_attempts" name="max_login_attempts" min="3" max="20" value="<?php echo htmlspecialchars($system_settings['max_login_attempts']['setting_value'] ?? '5'); ?>">
                            </div>
                            <div class="setting-group">
                                <label for="lockout_duration">Lockout Duration (minutes)</label>
                                <input type="number" id="lockout_duration" name="lockout_duration" min="5" max="1440" value="<?php echo htmlspecialchars($system_settings['lockout_duration']['setting_value'] ?? '15'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Settings -->
                <div id="email" class="tab-content">
                    <div class="settings-section">
                        <h3><i class="fas fa-envelope"></i> Email Settings</h3>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="smtp_host">SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($system_settings['smtp_host']['setting_value'] ?? ''); ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="setting-group">
                                <label for="smtp_port">SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($system_settings['smtp_port']['setting_value'] ?? '587'); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="smtp_username">SMTP Username</label>
                                <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($system_settings['smtp_username']['setting_value'] ?? ''); ?>">
                            </div>
                            <div class="setting-group">
                                <label for="smtp_encryption">SMTP Encryption</label>
                                <select id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php echo ($system_settings['smtp_encryption']['setting_value'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($system_settings['smtp_encryption']['setting_value'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="none" <?php echo ($system_settings['smtp_encryption']['setting_value'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                        </div>
                        <div class="setting-group">
                            <label for="smtp_password">SMTP Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($system_settings['smtp_password']['setting_value'] ?? ''); ?>" placeholder="Leave empty to keep current password">
                        </div>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="email_from_address">From Email Address</label>
                                <input type="email" id="email_from_address" name="email_from_address" value="<?php echo htmlspecialchars($system_settings['email_from_address']['setting_value'] ?? ''); ?>">
                            </div>
                            <div class="setting-group">
                                <label for="email_from_name">From Name</label>
                                <input type="text" id="email_from_name" name="email_from_name" value="<?php echo htmlspecialchars($system_settings['email_from_name']['setting_value'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup Settings -->
                <div id="backup" class="tab-content">
                    <div class="settings-section">
                        <h3><i class="fas fa-database"></i> Backup Settings</h3>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="backup_frequency">Backup Frequency</label>
                                <select id="backup_frequency" name="backup_frequency">
                                    <option value="daily" <?php echo ($system_settings['backup_frequency']['setting_value'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo ($system_settings['backup_frequency']['setting_value'] ?? 'daily') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo ($system_settings['backup_frequency']['setting_value'] ?? 'daily') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            <div class="setting-group">
                                <label for="backup_retention">Backup Retention (days)</label>
                                <input type="number" id="backup_retention" name="backup_retention" min="7" max="365" value="<?php echo htmlspecialchars($system_settings['backup_retention']['setting_value'] ?? '30'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications Settings -->
                <div id="notifications" class="tab-content">
                    <div class="settings-section">
                        <h3><i class="fas fa-bell"></i> Notification Settings</h3>
                        <div class="setting-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="enable_notifications" name="enable_notifications" value="1" <?php echo ($system_settings['enable_notifications']['setting_value'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label for="enable_notifications">Enable System Notifications</label>
                            </div>
                        </div>
                        <div class="setting-group">
                            <label for="admin_email">Administrator Email</label>
                            <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($system_settings['admin_email']['setting_value'] ?? ''); ?>" placeholder="admin@example.com">
                            <small style="color: #666; display: block; margin-top: 5px;">Email address for system notifications and alerts.</small>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" name="update_system_settings" class="btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                        <i class="fas fa-save"></i> Save System Settings
                    </button>
                </div>
            </form>

        <?php else: ?>
            <!-- User Settings -->
            <div class="settings-tabs">
                <button class="tab-button active" onclick="showTab('preferences')">Preferences</button>
                <button class="tab-button" onclick="showTab('account')">Account</button>
                <button class="tab-button" onclick="showTab('danger')">Danger Zone</button>
            </div>

            <!-- User Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Account Age</h4>
                    <div class="value"><?php echo $stats['account_age']; ?> days</div>
                </div>
                <div class="stat-card">
                    <h4>Journal Entries</h4>
                    <div class="value"><?php echo $stats['total_entries']; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Appointments</h4>
                    <div class="value"><?php echo $stats['total_appointments']; ?></div>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div id="preferences" class="tab-content active">
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="settings-section">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <div class="setting-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php echo ($_SESSION['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="email_notifications">Email Notifications</label>
                            </div>
                        </div>
                        <div class="setting-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="sms_notifications" name="sms_notifications" value="1" <?php echo ($_SESSION['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                <label for="sms_notifications">SMS Notifications</label>
                            </div>
                        </div>
                        <div class="setting-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="push_notifications" name="push_notifications" value="1" <?php echo ($_SESSION['push_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                <label for="push_notifications">Push Notifications</label>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-palette"></i> Appearance</h3>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="theme">Theme</label>
                                <select id="theme" name="theme">
                                    <option value="light" <?php echo ($_SESSION['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="dark" <?php echo ($_SESSION['theme'] ?? 'light') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                    <option value="auto" <?php echo ($_SESSION['theme'] ?? 'light') === 'auto' ? 'selected' : ''; ?>>Auto</option>
                                </select>
                            </div>
                            <div class="setting-group">
                                <label for="language">Language</label>
                                <select id="language" name="language">
                                    <option value="en" <?php echo ($_SESSION['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="es" <?php echo ($_SESSION['language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>Español</option>
                                    <option value="fr" <?php echo ($_SESSION['language'] ?? 'en') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                    <option value="de" <?php echo ($_SESSION['language'] ?? 'en') === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-lock"></i> Privacy & Security</h3>
                        <div class="form-row">
                            <div class="setting-group">
                                <label for="privacy">Privacy Level</label>
                                <select id="privacy" name="privacy">
                                    <option value="public" <?php echo ($_SESSION['privacy'] ?? 'private') === 'public' ? 'selected' : ''; ?>>Public</option>
                                    <option value="private" <?php echo ($_SESSION['privacy'] ?? 'private') === 'private' ? 'selected' : ''; ?>>Private</option>
                                    <option value="friends" <?php echo ($_SESSION['privacy'] ?? 'private') === 'friends' ? 'selected' : ''; ?>>Friends Only</option>
                                </select>
                            </div>
                            <div class="setting-group">
                                <label for="session_timeout">Session Timeout (minutes)</label>
                                <input type="number" id="session_timeout" name="session_timeout" min="15" max="120" value="<?php echo $_SESSION['session_timeout'] ?? 30; ?>">
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" name="update_user_settings" class="btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Account Tab -->
            <div id="account" class="tab-content">
                <div class="settings-section">
                    <h3><i class="fas fa-user"></i> Account Information</h3>
                    <div class="form-row">
                        <div class="setting-group">
                            <label>Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" readonly>
                        </div>
                        <div class="setting-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="setting-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo htmlspecialchars(ucfirst($user_data['role'] ?? 'user')); ?>" readonly>
                        </div>
                        <div class="setting-group">
                            <label>Member Since</label>
                            <input type="text" value="<?php echo date('M d, Y', strtotime($user_data['created_at'] ?? 'now')); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone Tab -->
            <div id="danger" class="tab-content">
                <div class="settings-section danger-zone">
                    <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                    <p style="color: #dc3545; margin-bottom: 1rem;">These actions are permanent and cannot be undone.</p>

                    <form action="" method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="setting-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <small style="color: #666; display: block; margin-top: 5px;">Enter your password to confirm account deletion.</small>
                        </div>
                        <button type="submit" name="delete_account" class="btn-danger">
                            <i class="fas fa-trash"></i> Delete Account Permanently
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => button.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>

</body>
</html>