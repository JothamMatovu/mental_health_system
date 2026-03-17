<?php
// backup.php - Backup and Restore System
// This backup stuff should work, I hope
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$uname = $_SESSION['name'];
include '../config/config.php';

// Create backups directory if it doesn't exist
$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}
// Just in case
$temp_dir = $backup_dir;

$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle backup creation
if (isset($_POST['create_backup'])) {
    $backup_type = $_POST['backup_type'] ?? 'full';
    $timestamp = date('Y-m-d_H-i-s');
    $backup_name = "backup_{$timestamp}";

    try {
        $success_count = 0;

        // Database backup
        if ($backup_type === 'database' || $backup_type === 'full') {
            $db_backup_file = $backup_dir . $backup_name . '_database.sql';

            // Use mysqldump command with better error handling
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s %s > "%s" 2>&1',
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_NAME),
                $db_backup_file
            );

            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);

            if ($return_var === 0 && file_exists($db_backup_file) && filesize($db_backup_file) > 0) {
                $success_count++;

                // Log admin action
                $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $desc = "Admin '{$uname}' created database backup: {$backup_name}_database.sql";
                $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                $log_stmt->execute();
                $log_stmt->close();
            } else {
                $error_details = !empty($output) ? implode("\n", $output) : "Unknown error";
                throw new Exception("Database backup failed: " . $error_details);
            }
        }
                
                // Log admin action
                $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $desc = "Admin '{$uname}' created database backup: {$backup_name}_database.sql";
                $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                $log_stmt->execute();
                $log_stmt->close();
            } else {
                throw new Exception("Database backup failed");
            }
        }
        
        // Files backup
        if ($backup_type === 'files' || $backup_type === 'full') {
            $files_backup_file = $backup_dir . $backup_name . '_files.zip';
            
            $zip = new ZipArchive();
            if ($zip->open($files_backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $resources_dir = __DIR__ . '/resources/';
                if (is_dir($resources_dir)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($resources_dir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = 'resources/' . substr($filePath, strlen($resources_dir));
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }
                
                $zip->close();
                
                if (file_exists($files_backup_file) && filesize($files_backup_file) > 0) {
                    $success_count++;
                    
                    // Log admin action
                    $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $desc = "Admin '{$uname}' created files backup: {$backup_name}_files.zip";
                    $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                    $log_stmt->execute();
                    $log_stmt->close();
                } else {
                    throw new Exception("Files backup failed");
                }
            } else {
                throw new Exception("Could not create ZIP archive");
            }
        }
        
        if ($success_count > 0) {
            $message = "Backup created successfully! ";
            if ($backup_type === 'database' || $backup_type === 'full') {
                $message .= "Database backup: {$backup_name}_database.sql ";
            }
            if ($backup_type === 'files' || $backup_type === 'full') {
                $message .= "Files backup: {$backup_name}_files.zip";
            }

            // Auto-cleanup old backups based on system settings
            cleanupOldBackups($backup_dir, $conn);
        }
        
    } catch (Exception $e) {
        $error = "Backup failed: " . $e->getMessage();
    }
}

// Handle backup download
if ($action === 'download' && isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = $backup_dir . $filename;
    
    if (file_exists($filepath) && strpos($filepath, $backup_dir) === 0) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $error = "Backup file not found";
    }
}

// Handle backup deletion
if ($action === 'delete' && isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = $backup_dir . $filename;
    
    if (file_exists($filepath) && strpos($filepath, $backup_dir) === 0) {
        if (unlink($filepath)) {
            $message = "Backup file deleted successfully";
            
            // Log admin action
            $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'];
            $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $desc = "Admin '{$uname}' deleted backup file: {$filename}";
            $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $error = "Failed to delete backup file";
        }
    } else {
        $error = "Backup file not found";
    }
}

// Handle restore
if (isset($_POST['restore_backup'])) {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $temp_file = $_FILES['backup_file']['tmp_name'];
        $original_name = $_FILES['backup_file']['name'];
        
        $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        try {
        try {
            if ($file_extension === 'sql') {
                // Database restore with better error handling
                $sql_content = file_get_contents($temp_file);

                if (empty($sql_content)) {
                    throw new Exception("SQL file is empty or unreadable");
                }

                // Split SQL file into individual statements (handle large files)
                $statements = [];
                $lines = explode("\n", $sql_content);
                $current_statement = "";
                $in_multiline_comment = false;

                foreach ($lines as $line) {
                    $line = trim($line);

                    // Skip comments and empty lines
                    if (empty($line) || strpos($line, '--') === 0 || strpos($line, '#') === 0) {
                        continue;
                    }

                    // Handle multiline comments
                    if (strpos($line, '/*') !== false) {
                        $in_multiline_comment = true;
                    }
                    if ($in_multiline_comment) {
                        if (strpos($line, '*/') !== false) {
                            $in_multiline_comment = false;
                        }
                        continue;
                    }

                    $current_statement .= $line . " ";

                    if (substr($line, -1) === ';') {
                        $statements[] = trim($current_statement);
                        $current_statement = "";
                    }
                }

                $conn->begin_transaction();
                $executed_count = 0;

                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^(DELIMITER|USE|SET|COMMIT|ROLLBACK)/i', $statement)) {
                        if ($conn->query($statement) === FALSE) {
                            throw new Exception("SQL Error on statement {$executed_count}: " . $conn->error . "\nStatement: " . substr($statement, 0, 100) . "...");
                        }
                        $executed_count++;
                    }
                }

                $conn->commit();
                $message = "Database restored successfully from: {$original_name} ({$executed_count} statements executed)";
                
                // Log admin action
                $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $desc = "Admin '{$uname}' restored database from: {$original_name}";
                $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                $log_stmt->execute();
                $log_stmt->close();
                
            } elseif ($file_extension === 'zip') {
                // Files restore with better validation
                $zip = new ZipArchive();
                if ($zip->open($temp_file) === TRUE) {
                    $extract_path = __DIR__ . '/';
                    $resources_dir = __DIR__ . '/resources/';

                    // Validate ZIP contents before extraction
                    $valid_files = 0;
                    $invalid_files = 0;

                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);

                        // Security check: prevent directory traversal
                        if (strpos($filename, '..') !== false || strpos($filename, '/') === 0 || strpos($filename, '\\') === 0) {
                            $invalid_files++;
                            continue;
                        }

                        // Only allow files in resources/ directory or subdirectories
                        if (strpos($filename, 'resources/') === 0 || strpos($filename, 'resources\\') === 0) {
                            $valid_files++;
                        } else {
                            $invalid_files++;
                        }
                    }

                    if ($invalid_files > 0) {
                        throw new Exception("ZIP file contains {$invalid_files} invalid files. Only files in the 'resources/' directory are allowed.");
                    }

                    if ($valid_files === 0) {
                        throw new Exception("ZIP file contains no valid files to restore.");
                    }

                    // Clear existing resources directory
                    if (is_dir($resources_dir)) {
                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($resources_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::CHILD_FIRST
                        );

                        foreach ($files as $fileinfo) {
                            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                            $todo($fileinfo->getRealPath());
                        }
                    }

                    // Extract ZIP
                    if ($zip->extractTo($extract_path) === TRUE) {
                        $zip->close();
                        $message = "Files restored successfully from: {$original_name} ({$valid_files} files extracted)";
                    } else {
                        throw new Exception("Failed to extract ZIP file");
                    }
                    
                    // Log admin action
                    $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $desc = "Admin '{$uname}' restored files from: {$original_name}";
                    $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                } else {
                    throw new Exception("Could not open ZIP file");
                }
            } else {
                throw new Exception("Unsupported file type. Only .sql and .zip files are supported");
            }
            
        } catch (Exception $e) {
            $error = "Restore failed: " . $e->getMessage();
        }
    } else {
        $error = "No backup file uploaded or upload failed";
    }
}

// Get backup statistics
$backup_stats = [
    'last_backup' => null,
    'total_backups' => count($backup_files),
    'total_size' => 0,
    'next_scheduled' => null
];

foreach ($backup_files as $file) {
    $backup_stats['total_size'] += $file['size'];
    $backup_date = strtotime($file['date']);
    if ($backup_stats['last_backup'] === null || $backup_date > strtotime($backup_stats['last_backup'])) {
        $backup_stats['last_backup'] = $file['date'];
    }
}

// Get backup schedule from system settings
$result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'backup_frequency'");
if ($result && $row = $result->fetch_assoc()) {
    $frequency = $row['setting_value'];
    if ($backup_stats['last_backup']) {
        $last_backup_time = strtotime($backup_stats['last_backup']);
        switch ($frequency) {
            case 'daily':
                $backup_stats['next_scheduled'] = date('Y-m-d H:i:s', strtotime('+1 day', $last_backup_time));
                break;
            case 'weekly':
                $backup_stats['next_scheduled'] = date('Y-m-d H:i:s', strtotime('+1 week', $last_backup_time));
                break;
            case 'monthly':
                $backup_stats['next_scheduled'] = date('Y-m-d H:i:s', strtotime('+1 month', $last_backup_time));
                break;
        }
    }
}

$conn->close();
?>
<?php
// Function to cleanup old backups based on system settings
function cleanupOldBackups($backup_dir, $conn) {
    try {
        // Get backup retention setting
        $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'backup_retention'");
        if ($result && $row = $result->fetch_assoc()) {
            $retention_days = (int)$row['setting_value'];
            if ($retention_days > 0) {
                $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

                if (is_dir($backup_dir)) {
                    $files = scandir($backup_dir);
                    $deleted_count = 0;

                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && (strpos($file, '.sql') !== false || strpos($file, '.zip') !== false)) {
                            $filepath = $backup_dir . $file;
                            $file_date = date('Y-m-d H:i:s', filemtime($filepath));

                            if ($file_date < $cutoff_date) {
                                if (unlink($filepath)) {
                                    $deleted_count++;
                                }
                            }
                        }
                    }

                    if ($deleted_count > 0) {
                        // Log cleanup action
                        global $uname;
                        $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                        $desc = "System automatically deleted {$deleted_count} old backup files (retention: {$retention_days} days)";
                        $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail cleanup - don't interrupt backup process
        error_log("Backup cleanup failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>
    <nav class="navbar">
        <h1><i class="fas fa-shield-alt"></i> Admin Panel</h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($uname); ?></span>
            <span class="user-role">Administrator</span>
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <a href="../admin/admin_dashboard.php" class="btn-secondary">Dashboard</a>
        </div>
    </nav>
    <div class="container">
        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="message success" style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="message error" style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2><i class="fas fa-database"></i> Backup & Restore</h2>
            <p>Create backups of your system data and restore from previous backups when needed.</p>
        </div>

        <!-- Backup Creation Section -->
        <div class="settings-section">
            <h3><i class="fas fa-plus-circle"></i> Create New Backup</h3>
            <form action="" method="POST" id="backupForm">
                <div class="form-row">
                    <div class="setting-group">
                        <label for="backup_type">Backup Type</label>
                        <select id="backup_type" name="backup_type" required>
                            <option value="full">Full Backup (Database + Files)</option>
                            <option value="database">Database Only</option>
                            <option value="files">Files Only</option>
                        </select>
                    </div>
                    <div class="setting-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="create_backup" class="btn-primary" id="createBackupBtn">
                            <i class="fas fa-play"></i> Create Backup
                        </button>
                    </div>
                </div>
                <div class="backup-info">
                    <small style="color: #666;">
                        <i class="fas fa-info-circle"></i>
                        Full backups include both database and uploaded files. Database backups contain all user data, settings, and content. Files backups include resources, images, and documents.
                    </small>
                </div>
            </form>
        </div>

        <!-- Existing Backups Section -->
        <div class="settings-section">
            <h3><i class="fas fa-history"></i> Existing Backups</h3>

            <?php if (empty($backup_files)): ?>
                <div class="empty-state">
                    <i class="fas fa-archive"></i>
                    <h4>No Backups Found</h4>
                    <p>Create your first backup using the form above.</p>
                </div>
            <?php else: ?>
                <div class="backup-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count($backup_files); ?></span>
                        <span class="stat-label">Total Backups</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count(array_filter($backup_files, function($f) { return $f['type'] === 'Database'; })); ?></span>
                        <span class="stat-label">Database</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count(array_filter($backup_files, function($f) { return $f['type'] === 'Files'; })); ?></span>
                        <span class="stat-label">Files</span>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_files as $file): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($file['name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $file['type'] === 'Database' ? 'badge-primary' : 'badge-secondary'; ?>">
                                            <?php echo $file['type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($file['size'] / 1024, 1); ?> KB</td>
                                    <td><?php echo $file['date']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=download&file=<?php echo urlencode($file['name']); ?>" class="btn-action btn-download" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="?action=delete&file=<?php echo urlencode($file['name']); ?>" class="btn-action btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this backup?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Restore Section -->
        <div class="settings-section danger-zone">
            <h3><i class="fas fa-upload"></i> Restore from Backup</h3>
            <div class="warning-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Warning:</strong> Restoring from a backup will overwrite existing data. This action cannot be undone.
                    <ul>
                        <li>Database restores will replace all user data, settings, and content</li>
                        <li>File restores will replace all uploaded resources and documents</li>
                        <li>Always create a fresh backup before performing a restore</li>
                    </ul>
                </div>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" id="restoreForm">
                <div class="form-row">
                    <div class="setting-group">
                        <label for="backup_file">Select Backup File</label>
                        <input type="file" id="backup_file" name="backup_file" accept=".sql,.zip" required>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Supported formats: .sql (database) and .zip (files)
                        </small>
                    </div>
                    <div class="setting-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="restore_backup" class="btn-danger" id="restoreBackupBtn" onclick="return confirm('Are you sure you want to restore from this backup? This will overwrite existing data.')">
                            <i class="fas fa-upload"></i> Restore Backup
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- System Information -->
        <div class="settings-section">
            <h3><i class="fas fa-info-circle"></i> System Information</h3>
            <div class="system-info">
                <div class="info-row">
                    <span class="info-label">Backup Directory:</span>
                    <span class="info-value"><?php echo htmlspecialchars($backup_dir); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Available Space:</span>
                    <span class="info-value"><?php echo number_format(disk_free_space($backup_dir) / 1024 / 1024 / 1024, 2); ?> GB</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Backups:</span>
                    <span class="info-value"><?php echo $backup_stats['total_backups']; ?> files (<?php echo number_format($backup_stats['total_size'] / 1024 / 1024, 2); ?> MB)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Backup:</span>
                    <span class="info-value"><?php echo $backup_stats['last_backup'] ? $backup_stats['last_backup'] : 'Never'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Next Scheduled:</span>
                    <span class="info-value"><?php echo $backup_stats['next_scheduled'] ? $backup_stats['next_scheduled'] : 'Not scheduled'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Database:</span>
                    <span class="info-value"><?php echo DB_NAME; ?> (<?php echo DB_HOST; ?>)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Auto Cleanup:</span>
                    <span class="info-value">
                        <?php
                        $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'backup_retention'");
                        if ($result && $row = $result->fetch_assoc()) {
                            echo $row['setting_value'] . ' days retention';
                        } else {
                            echo 'Disabled';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <style>
        .settings-section { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .settings-section h3 { color: #333; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #667eea; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .setting-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        .setting-group input[type="file"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-primary { background-color: #667eea; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background-color: #5568d3; }
        .btn-danger { background-color: #dc3545; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-danger:hover { background-color: #c82333; }
        .backup-info { margin-top: 1rem; padding: 1rem; background-color: #f8f9fa; border-radius: 5px; }
        .empty-state { text-align: center; padding: 3rem; color: #666; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .backup-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-item { background: #f8f9fa; padding: 1rem; border-radius: 8px; text-align: center; }
        .stat-number { display: block; font-size: 2rem; font-weight: 700; color: #667eea; }
        .stat-label { color: #666; font-size: 0.9rem; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: 600; color: #333; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .badge-primary { background-color: #d4edda; color: #155724; }
        .badge-secondary { background-color: #cce5ff; color: #004085; }
        .action-buttons { display: flex; gap: 8px; }
        .btn-action { padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-download { background-color: #28a745; color: white; }
        .btn-download:hover { background-color: #218838; }
        .btn-delete { background-color: #dc3545; color: white; }
        .btn-delete:hover { background-color: #c82333; }
        .danger-zone { border: 2px solid #dc3545; background-color: #fff5f5; }
        .danger-zone h3 { color: #dc3545; border-bottom-color: #dc3545; }
        .warning-notice { background-color: #fff3cd; border: 1px solid #ffeeba; border-radius: 5px; padding: 1rem; margin-bottom: 1.5rem; display: flex; gap: 10px; align-items: flex-start; }
        .warning-notice i { color: #856404; font-size: 1.2rem; margin-top: 2px; }
        .warning-notice ul { margin: 0.5rem 0 0 0; padding-left: 1.2rem; }
        .system-info { display: grid; gap: 0.5rem; }
        .info-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
        .info-label { font-weight: 600; color: #555; }
        .info-value { color: #333; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .backup-stats { grid-template-columns: repeat(2, 1fr); }
            .action-buttons { flex-direction: column; }
        }
    </style>

    <script>
        // Add loading states to buttons
        document.getElementById('backupForm').addEventListener('submit', function() {
            const btn = document.getElementById('createBackupBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Backup...';
            btn.disabled = true;
        });

        document.getElementById('restoreForm').addEventListener('submit', function() {
            const btn = document.getElementById('restoreBackupBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Restoring...';
            btn.disabled = true;
        });

        // File type validation
        document.getElementById('backup_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const extension = file.name.split('.').pop().toLowerCase();
                if (!['sql', 'zip'].includes(extension)) {
                    alert('Please select a valid backup file (.sql or .zip)');
                    e.target.value = '';
                }
            }
        });
    </script>

</body>
</html>