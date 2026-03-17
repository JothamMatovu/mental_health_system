<?php
// register.php
session_start();
include __DIR__ . '/../config/config.php';

$error_message = "";
$success_message = "";

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
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = trim($_POST['role'] ?? 'user');

        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            $error_message = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error_message = "Email already registered.";
            } else {
                // Hash password and insert with selected role
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // Allow role selection but validate it's either 'user' or 'admin'
                $role = in_array($role, ['user', 'admin']) ? $role : 'user';
                
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

                if ($stmt->execute()) {
                    $success_message = "Registration successful! Redirecting to login...";
                    header("Refresh: 2; url=login.php");
                } else {
                    $error_message = "Registration failed. Please try again.";
                }
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body class="login-page">

    <div class="login-container">
        <div class="login-header">
            <h2>Create Account</h2>
            <p>Join our mental health support system</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-group">
                <label for="name">Full Name <span style="color: #dc3545;">*</span></label>
                <input type="text" id="name" name="name" required placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label for="email">Email Address <span style="color: #dc3545;">*</span></label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label for="password">Password <span style="color: #dc3545;">*</span></label>
                <input type="password" id="password" name="password" required placeholder="At least 6 characters">
                <div class="password-strength" id="passwordStrength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span style="color: #dc3545;">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
            </div>

            <div class="form-group">
                <label for="role">Account Type <span style="color: #dc3545;">*</span></label>
                <select id="role" name="role" required>
                    <option value="user">User - Access to personal dashboard and resources</option>
                    <option value="admin">Administrator - Full system access and management</option>
                </select>
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="login-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script>
        // Password Strength Indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthElement = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthElement.textContent = '';
                strengthElement.className = 'password-strength';
            } else if (password.length < 6) {
                strengthElement.textContent = 'Password must be at least 6 characters';
                strengthElement.className = 'password-strength weak';
            } else if (password.length < 8) {
                strengthElement.textContent = 'Password strength: Weak';
                strengthElement.className = 'password-strength weak';
            } else if (password.length < 12) {
                strengthElement.textContent = 'Password strength: Medium';
                strengthElement.className = 'password-strength medium';
            } else {
                strengthElement.textContent = 'Password strength: Strong';
                strengthElement.className = 'password-strength strong';
            }
        });
    </script>

</body>
</html>