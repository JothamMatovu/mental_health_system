<?php
// messages_send.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'];

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        // Sanitize and validate inputs
        $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);

        // Validation
        if (empty($receiver_id)) {
            $error_message = "Please select a recipient.";
        } elseif (empty($subject)) {
            $error_message = "Please enter a subject.";
        } elseif (empty($message)) {
            $error_message = "Please enter a message.";
        } elseif (strlen($message) < 10) {
            $error_message = "Message must be at least 10 characters.";
        } elseif (strlen($message) > 5000) {
            $error_message = "Message is too long (max 5000 characters).";
        } else {
            // Check if receiver exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $receiver_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error_message = "Invalid recipient. Please select a valid user.";
            } else {
                // Insert message into database
                $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $user_id, $receiver_id, $subject, $message);

                if ($stmt->execute()) {
                    $success_message = "Message sent successfully!";
                    // Redirect after 2 seconds
                    header("Refresh: 2; url=messages.php?action=inbox");
                } else {
                    $error_message = "Failed to send message. Please try again.";
                }
            }
            $stmt->close();
        }
    }
}

// Get list of users to send message to (excluding current user)
$users = [];
$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id != ? ORDER BY name ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Message - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-paper-plane"></i> Send Message</h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
                <a href="../admin/admin_dashboard.php" class="btn-secondary">Admin Panel</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="form-container">
            <!-- Page Header -->
            <div class="form-header">
                <h2>Send a New Message</h2>
                <p>Compose and send a message to a therapist or support staff</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="" method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="receiver_id">Recipient <span style="color: #dc3545;">*</span></label>
                    <select id="receiver_id" name="receiver_id" required>
                        <option value="">-- Select Recipient --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['name']); ?> 
                                <?php echo $user['role'] === 'admin' ? '(Admin)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help-text">Select the person you want to send a message to</p>
                </div>

                <div class="form-group">
                    <label for="subject">Subject <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="subject" name="subject" required placeholder="Enter message subject" maxlength="200">
                    <p class="help-text">Brief description of your message (max 200 characters)</p>
                </div>

                <div class="form-group">
                    <label for="message">Message <span style="color: #dc3545;">*</span></label>
                    <textarea id="message" name="message" required placeholder="Write your message here..." maxlength="5000"></textarea>
                    <div class="char-count">
                        <span id="charCount">0</span> / 5000 characters
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="messages.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Character Counter Script -->
    <script>
        document.getElementById('message').addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });
    </script>

</body>
</html>