<?php
// login.php - the login page, I made it secure I think
session_start();
include __DIR__ . '/../config/config.php';

// Initialize variables - starting fresh
$errormsg = "";
$successmsg = "";
$csrftoken = "";

// Generate CSRF token if not exists - for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrftoken = $_SESSION['csrf_token'];

// Rate limiting check (prevent brute force) - good to have
if (isset($_SESSION['login_attempts'])) {
    if ($_SESSION['login_attempts'] >= 5) {
        if (time() - $_SESSION['last_login_attempt'] < 300) { // 5 minutes
            $errormsg = "Too many failed attempts. Please try again in 5 minutes.";
        } else {
            $_SESSION['login_attempts'] = 0;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token - important
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errormsg = "Invalid security token.";
    } elseif (empty($_POST['email']) || empty($_POST['password'])) {
        $errormsg = "Please fill in all fields.";
    } else {
        // Sanitize inputs - clean them up
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $rememberme = isset($_POST['remember_me']) ? true : false;

        // Check rate limit - don't let them spam
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
            $errormsg = "Too many failed attempts. Please try again later.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $name, $hashed_password, $role);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    // Login Successful - Reset attempts - yay
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['last_login_attempt'] = time();

                    // Set session variables - store user info
                    $_SESSION['user_id'] = $id;
                    $_SESSION['name'] = $name;
                    $_SESSION['role'] = $role;
                    $_SESSION['login_time'] = time();

                    // Log successful login - for tracking
                    $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'login', ?, ?, ?)");
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $desc = "User '{$name}' logged in successfully";
                    $log_stmt->bind_param("isss", $id, $desc, $ip, $useragent);
                    $log_stmt->execute();
                    $log_stmt->close();

                    // Remember Me functionality - if they want
                    if ($rememberme) {
                        setcookie('user_id', $id, time() + (86400 * 30), '/'); // 30 days
                    }

                    // Redirect based on role - admins go to admin page
                    if ($role === 'admin') {
                        header("Location: ../admin/admin_dashboard.php");
                    } else {
                        header("Location: ../user/user_dashboard.php");
                    }
                    exit();
                } else {
                    // Increment failed attempts - bad password
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    $_SESSION['last_login_attempt'] = time();
                    
                    // Log failed login - record it
                    $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (?, 'failed_login', ?, ?, ?)");
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $desc = "Failed login attempt - incorrect password";
                    $log_stmt->bind_param("isss", $id, $desc, $ip, $useragent);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    $errormsg = "Incorrect password!";
                }
            } else {
                // Increment failed attempts - email not found
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['last_login_attempt'] = time();
                
                // Log failed login - log it
                $log_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) VALUES (NULL, 'failed_login', ?, ?, ?)");
                $ip = $_SERVER['REMOTE_ADDR'];
                $useragent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $desc = "Failed login attempt - email not found: " . $email;
                $log_stmt->bind_param("sss", $desc, $ip, $useragent);
                $log_stmt->execute();
                $log_stmt->close();
                
                $errormsg = "Email not found!";
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
    <title>Login - Mental Health System</title>
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body class="login-page">

    <div class="login-container">
        <div class="login-header">
            <h2>Welcome Back</h2>
            <p>Please login to your Mental Health Account</p>
        </div>

        <!-- Display Error or Success Messages -->
        <?php if (!empty($errormsg)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($errormsg); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($successmsg)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successmsg); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="loginForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrftoken); ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email" autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
                <span class="password-toggle" onclick="togglePassword()"><i class="fas fa-eye"></i></span>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">Remember me for 30 days</label>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <span class="spinner" id="loadingSpinner"></span>
                <span id="btnText">Login</span>
            </button>
        </form>

        <div class="login-footer">
            <p>Don't have an account? <a href="register.php">Sign up</a></p>
            <p><a href="forgot_password.php">Forgot Password?</a></p>
        </div>
    </div>

    <script>
        // Password Visibility Toggle - useful feature
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.querySelector('i').classList.remove('fa-eye');
                toggleIcon.querySelector('i').classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.querySelector('i').classList.remove('fa-eye-slash');
                toggleIcon.querySelector('i').classList.add('fa-eye');
            }
        }

        // Loading State on Submit - makes it look nice
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const spinner = document.getElementById('loadingSpinner');
            const btnText = document.getElementById('btnText');
            
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Logging in...';
        });
    </script>

</body>
</html>