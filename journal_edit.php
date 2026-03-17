<?php
// journal_edit.php - for editing journal entries, I hope this is secure
session_start();

// Check if user is logged in - gotta make sure
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userid = $_SESSION['user_id']; // the user's id
$error_msg = ""; // for errors
$success_msg = ""; // for success

// Get entry ID
$entryid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($entryid <= 0) {
    header("Location: journal.php");
    exit();
}

// Include database connection
include '../config/config.php';

// Fetch existing entry - let's get the data
$stmt = $conn->prepare("SELECT * FROM journal_entries WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $entryid, $userid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Entry not found or doesn't belong to user - redirect
    header("Location: journal.php");
    exit();
}

$entry = $result->fetch_assoc();
$stmt->close();

// Handle form submission - when they submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $moodrating = isset($_POST['mood_rating']) ? (int)$_POST['mood_rating'] : null;

    // Validation - check if content is there
    if (empty($content)) {
        $error_msg = "Please write something in your journal.";
    } else {
        // Update entry - save the changes
        $stmt = $conn->prepare("UPDATE journal_entries SET title = ?, content = ?, mood_rating = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("siiii", $title, $content, $moodrating, $entryid, $userid);

        if ($stmt->execute()) {
            $success_msg = "Entry updated successfully!";
            // Redirect after 2 seconds - give them time to see the message
            header("Refresh: 2; url=journal.php");
        } else {
            $error_msg = "Failed to update entry. Please try again.";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Journal Entry - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-edit"></i> Edit Journal Entry</h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($entry['name'] ?? $_SESSION['name']); ?></span>
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
                <h2>Edit Your Journal Entry</h2>
                <p>Update your thoughts, feelings, and mood rating</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="" method="POST">
                <div class="form-group">
                    <label for="title">Entry Title <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($entry['title'] ?? ''); ?>" placeholder="Give your entry a title (optional)">
                </div>

                <div class="form-group">
                    <label for="content">Your Thoughts <span style="color: #dc3545;">*</span></label>
                    <textarea id="content" name="content" required placeholder="Write about your day, feelings, or anything on your mind..."><?php echo htmlspecialchars($entry['content']); ?></textarea>
                    <div class="char-count">
                        <span id="charCount"><?php echo strlen($entry['content']); ?></span> characters
                    </div>
                </div>

                <div class="form-group">
                    <label for="mood_rating">How are you feeling today?</label>
                    <select id="mood_rating" name="mood_rating">
                        <option value="">-- Select Mood --</option>
                        <option value="5" <?php echo ($entry['mood_rating'] == 5) ? 'selected' : ''; ?>>
                             Excellent (5/5)
                        </option>
                        <option value="4" <?php echo ($entry['mood_rating'] == 4) ? 'selected' : ''; ?>>
                             Good (4/5)
                        </option>
                        <option value="3" <?php echo ($entry['mood_rating'] == 3) ? 'selected' : ''; ?>>
                             Okay (3/5)
                        </option>
                        <option value="2" <?php echo ($entry['mood_rating'] == 2) ? 'selected' : ''; ?>>
                             Not Great (2/5)
                        </option>
                        <option value="1" <?php echo ($entry['mood_rating'] == 1) ? 'selected' : ''; ?>>
                             Bad (1/5)
                        </option>
                    </select>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="journal.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Character Counter Script -->
    <script>
        document.getElementById('content').addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });
    </script>

</body>
</html>