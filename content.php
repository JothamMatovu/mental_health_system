<?php
// content.php - Content Management for Admins
// Content management, this should be useful
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$uname = $_SESSION['name'];
// Just in case
$temp_user = $uname;
include '../config/config.php';

$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$resource_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle Add/Edit Resource
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($description) || empty($category)) {
        $error = "All fields are required.";
    } else {
        if (isset($_POST['resource_id']) && $_POST['resource_id'] > 0) {
            // Update existing resource
            $rid = (int)$_POST['resource_id'];
            $stmt = $conn->prepare("UPDATE resources SET title=?, description=?, category=?, content=? WHERE id=?");
            $stmt->bind_param("ssssi", $title, $description, $category, $content, $rid);
            
            if ($stmt->execute()) {
                $message = "Resource updated successfully!";
                
                // Log admin action
                $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $desc = "Admin '{$uname}' updated resource: {$title}";
                $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                $log_stmt->execute();
                $log_stmt->close();
                
                $action = 'list';
            } else {
                $error = "Failed to update resource: " . $conn->error;
            }
            $stmt->close();
        } else {
            // Add new resource
            $stmt = $conn->prepare("INSERT INTO resources (title, description, category, content, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $title, $description, $category, $content);
            
            if ($stmt->execute()) {
                $message = "Resource added successfully!";
                
                // Log admin action
                $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $desc = "Admin '{$uname}' added new resource: {$title}";
                $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                $log_stmt->execute();
                $log_stmt->close();
                
                $action = 'list';
            } else {
                $error = "Failed to add resource: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle Delete Resource
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $delete_id = (int)$_GET['delete'];
    
    // Get resource title for logging
    $get_title = $conn->prepare("SELECT title FROM resources WHERE id=?");
    $get_title->bind_param("i", $delete_id);
    $get_title->execute();
    $title_result = $get_title->get_result();
    $resource_title = "Unknown";
    if ($title_row = $title_result->fetch_assoc()) {
        $resource_title = $title_row['title'];
    }
    $get_title->close();
    
    $stmt = $conn->prepare("DELETE FROM resources WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $message = "Resource deleted successfully!";
        
        // Log admin action
        $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'];
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $desc = "Admin '{$uname}' deleted resource: {$resource_title}";
        $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
        $log_stmt->execute();
        $log_stmt->close();
    } else {
        $error = "Failed to delete resource: " . $conn->error;
    }
    $stmt->close();
    $action = 'list';
}

// Get all resources for listing
$resources = [];
$result = $conn->query("SELECT id, title, category, created_at FROM resources ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $resources[] = $row;
    }
}

// Get resource for editing
$edit_resource = null;
if ($action === 'edit' && $resource_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM resources WHERE id=?");
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_resource = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
    <style>
        .admin-nav { display: flex; gap: 10px; margin-bottom: 2rem; flex-wrap: wrap; }
        .admin-nav a { padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .admin-nav a:hover { background: #764ba2; }
        .admin-nav a.active { background: #764ba2; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
    </style>
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
        <div class="page-header">
            <h2>Content Management</h2>
            <p>Manage resources (articles, videos, exercises, meditation)</p>
        </div>

        <!-- Navigation Tabs -->
        <!-- TODO: make this nav look better -->
        <div class="admin-nav">
            <a href="content.php?action=list" class="<?php echo ($action === 'list') ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All Resources
            </a>
            <a href="content.php?action=add" class="<?php echo ($action === 'add') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i> Add New Resource
            </a>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- List Resources -->
        <?php if ($action === 'list'): ?>
            <?php if (empty($resources)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Resources Found</h3>
                    <p><a href="content.php?action=add" style="color: #667eea; font-weight: 600;">Create your first resource</a></p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resources as $res): ?>
                                <tr>
                                    <td><?php echo $res['id']; ?></td>
                                    <td><?php echo htmlspecialchars($res['title']); ?></td>
                                    <td>
                                        <span class="badge" style="background-color: #e3f2fd; color: #2196f3; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem;">
                                            <?php echo htmlspecialchars(ucfirst($res['category'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($res['created_at'])); ?></td>
                                    <td>
                                        <a href="content.php?action=edit&id=<?php echo $res['id']; ?>" class="action-btn btn-edit">Edit</a>
                                        <a href="content.php?delete=<?php echo $res['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure? This cannot be undone.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <!-- Add/Edit Form -->
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <div class="form-container">
                <form action="" method="POST">
                    <input type="hidden" name="resource_id" value="<?php echo ($edit_resource) ? $edit_resource['id'] : 0; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Title <span style="color: #dc3545;">*</span></label>
                            <input type="text" id="title" name="title" required placeholder="Resource title" value="<?php echo ($edit_resource) ? htmlspecialchars($edit_resource['title']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="category">Category <span style="color: #dc3545;">*</span></label>
                            <select id="category" name="category" required>
                                <option value="">Select a category</option>
                                <option value="article" <?php echo ($edit_resource && $edit_resource['category'] === 'article') ? 'selected' : ''; ?>>Article</option>
                                <option value="video" <?php echo ($edit_resource && $edit_resource['category'] === 'video') ? 'selected' : ''; ?>>Video</option>
                                <option value="exercise" <?php echo ($edit_resource && $edit_resource['category'] === 'exercise') ? 'selected' : ''; ?>>Exercise</option>
                                <option value="meditation" <?php echo ($edit_resource && $edit_resource['category'] === 'meditation') ? 'selected' : ''; ?>>Meditation</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description <span style="color: #dc3545;">*</span></label>
                        <textarea id="description" name="description" required placeholder="Short description (shown in listings)" rows="3"><?php echo ($edit_resource) ? htmlspecialchars($edit_resource['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="content">Full Content <span style="color: #dc3545;">*</span></label>
                        <textarea id="content" name="content" required placeholder="Full resource content" rows="8"><?php echo ($edit_resource) ? htmlspecialchars($edit_resource['content']) : ''; ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> <?php echo ($edit_resource) ? 'Update Resource' : 'Add Resource'; ?>
                        </button>
                        <a href="content.php?action=list" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>