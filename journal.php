<?php
// journal.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];

// Include database connection
include '../config/config.php';

// Get all journal entries for this user
$entries = [];
$result = $conn->query("SELECT * FROM journal_entries WHERE user_id = $user_id ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
}

// Get mood statistics
$mood_stats = ['average' => 0, 'count' => 0];
if (!empty($entries)) {
    $mood_ids = implode(',', array_column($entries, 'mood_rating'));
    $result = $conn->query("SELECT AVG(mood_rating) as avg, COUNT(*) as count FROM journal_entries WHERE user_id = $user_id AND mood_rating IS NOT NULL");
    if ($result) {
        $mood_stats = $result->fetch_assoc();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-book"></i> Journal</h1>
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
        <!-- Page Header -->
        <div class="page-header">
            <h2>My Journal</h2>
            <a href="journal_add.php" class="btn-new">
                <i class="fas fa-plus"></i> New Entry
            </a>
        </div>

        <!-- Mood Statistics -->
        <div class="mood-stats">
            <div class="stat-item">
                <div class="value"><?php echo number_format($mood_stats['average'], 1); ?></div>
                <div class="label">Average Mood (1-5)</div>
            </div>
            <div class="stat-item">
                <div class="value"><?php echo count($entries); ?></div>
                <div class="label">Total Entries</div>
            </div>
            <div class="stat-item">
                <div class="value"><?php echo !empty($entries) ? date('M d, Y', strtotime($entries[0]['created_at'])) : 'N/A'; ?></div>
                <div class="label">Last Entry</div>
            </div>
        </div>

        <!-- Journal Entries -->
        <div class="entries-grid">
            <?php if (empty($entries)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Journal Entries Yet</h3>
                    <p>Start writing your thoughts and feelings today!</p>
                    <a href="journal_add.php" class="btn-new">
                        <i class="fas fa-plus"></i> Create First Entry
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <div>
                                <h3 class="entry-title"><?php echo htmlspecialchars($entry['title'] ?? 'Untitled Entry'); ?></h3>
                                <div class="entry-date">
                                    <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($entry['created_at'])); ?>
                                </div>
                            </div>
                            <div class="entry-actions">
                                <a href="journal_edit.php?id=<?php echo $entry['id']; ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="journal_delete.php?id=<?php echo $entry['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this entry?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                        <div class="entry-content"><?php echo htmlspecialchars($entry['content']); ?></div>
                        <div class="entry-footer">
                            <?php if ($entry['mood_rating']): ?>
                                <span class="mood-badge mood-<?php echo $entry['mood_rating']; ?>">
                                    <i class="fas fa-smile"></i> Mood: <?php echo $entry['mood_rating']; ?>/5
                                </span>
                            <?php else: ?>
                                <span class="mood-badge" style="background-color: #f8f9fa; color: #666;">
                                    <i class="fas fa-question"></i> No mood rating
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>