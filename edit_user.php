<?php
// edit_user.php
// Editing users, hope I don't mess up
session_start();

// ensure admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$uname = $_SESSION['name'];

include '../config/config.php';

$userid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Just in case
$temp_id = $userid;
$message = '';
$error = '';
$user = null;

// Get user data
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id=?");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    header("Location: manage_users.php?error=User not found");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (empty($name) || empty($email) || empty($role)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!in_array($role, ['user', 'admin'])) {
        $error = "Invalid role selected.";
    } else {
        // Check if email is already used by another user
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id != ?");
        $check_stmt->bind_param("si", $email, $userid);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "This email is already in use.";
        } else {
            // Update user
            $update_stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
            $update_stmt->bind_param("sssi", $name, $email, $role, $userid);
            
            if ($update_stmt->execute()) {
                // Log admin action - note what was changed
                $changes = [];
                if ($user['name'] !== $name) $changes[] = "name changed from '" . $user['name'] . "' to '" . $name . "'";
                if ($user['email'] !== $email) $changes[] = "email changed from '" . $user['email'] . "' to '" . $email . "'";
                if ($user['role'] !== $role) $changes[] = "role changed from '" . $user['role'] . "' to '" . $role . "'";
                
                $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'admin_action', ?, ?, ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $desc = "Admin '" . $uname . "' updated user '" . $name . "': " . implode(", ", $changes);
                $log_stmt->bind_param("isss", $_SESSION['user_id'], $desc, $ip, $useragent);
                $log_stmt->execute();
                $log_stmt->close();
                
                $message = "User updated successfully!";
                $user = ['id' => $userid, 'name' => $name, 'email' => $email, 'role' => $role];
            } else {
                $error = "Failed to update user: " . $conn->error;
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin</title>
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
            <a href="../manage_users.php" class="btn-secondary">Back to Users</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Edit User</h2>
            <p>Update user details and permissions.</p>
        </div>
        <!-- TODO: add validation on frontend too -->

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="role">Account Type *</label>
                    <select id="role" name="role" required>
                        <option value="">-- Select Role --</option>
                        <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary">Update User</button>
                <a href="manage_users.php" class="btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

</body>
</html>
