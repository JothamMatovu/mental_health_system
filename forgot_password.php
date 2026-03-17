<?php
// forgot_password.php
session_start();
include __DIR__ . '/../config/config.php';

$error_message = "";
$success_message = "";
$step = 1; // Step 1: Email verification, Step 2: Password reset

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        // Step 1: User submits email
        if (isset($_POST['email']) && empty($_POST['reset_token'])) {
            $email = trim($_POST['email']);
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Please enter a valid email address.";
            } else {
                // Check if email exists
                $stmt = $conn->prepare("SELECT id, name FROM users WHERE email=?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_token_expiry=? WHERE email=?");
                    $stmt->bind_param("sss", $reset_token, $token_expiry, $email);
                    $stmt->execute();
                    
                    // In a real app, send email with reset link
                    // For now, show the token to user (not recommended in production)
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_token'] = $reset_token;
                    
                    $success_message = "A password reset link has been sent to your email. Use the token below to reset your password.";
                    $step = 2;
                    
                    // For testing purposes, display the token (REMOVE IN PRODUCTION)
                    $success_message .= " <br><strong>Reset Token (for testing): " . htmlspecialchars($reset_token) . "</strong>";
                } else {
                    $error_message = "Email address not found in our system.";
                }
                $stmt->close();
            }
        }
        // Step 2: User resets password with token
        elseif (isset($_POST['reset_token']) && isset($_POST['new_password'])) {
            $reset_token = trim($_POST['reset_token']);
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $email = $_SESSION['reset_email'] ?? '';

            if (empty($reset_token) || empty($new_password) || empty($confirm_password)) {
                $error_message = "All fields are required.";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "Passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $error_message = "Password must be at least 6 characters.";
            } else {
                // Verify token
                $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND reset_token=? AND reset_token_expiry > NOW()");
                $stmt->bind_param("ss", $email, $reset_token);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_token_expiry=NULL WHERE email=?");
                    $stmt->bind_param("ss", $hashed_password, $email);
                    $stmt->execute();
                    
                    $success_message = "Your password has been successfully reset! Redirecting to login...";
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_token']);
                    header("Refresh: 2; url=login.php");
                } else {
                    $error_message = "Invalid or expired reset token. Please try again.";
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body class="login-page">

    <div class="login-container">
        <div class="login-header">
            <h2>Reset Password</h2>
            <p><?php echo ($step === 1) ? 'Enter your email to reset your password' : 'Enter your new password'; ?></p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="resetForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <?php if ($step === 1): ?>
                <!-- Step 1: Email Verification -->
                <div class="form-group">
                    <label for="email">Email Address <span style="color: #dc3545;">*</span></label>
                    <input type="email" id="email" name="email" required placeholder="Enter your registered email" autocomplete="email">
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>

            <?php elseif ($step === 2 && !empty($_SESSION['reset_token'])): ?>
                <!-- Step 2: Password Reset -->
                <div class="form-group">
                    <label for="reset_token">Reset Token <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="reset_token" name="reset_token" required placeholder="Enter the reset token sent to your email" value="">
                </div>

                <div class="form-group">
                    <label for="new_password">New Password <span style="color: #dc3545;">*</span></label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter your new password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span style="color: #dc3545;">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your new password">
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-lock"></i> Reset Password
                </button>

            <?php endif; ?>
        </form>

        <div class="login-footer">
            <p><a href="login.php">Back to Login</a></p>
            <p>Don't have an account? <a href="register.php">Sign up</a></p>
        </div>
    </div>

</body>
</html>