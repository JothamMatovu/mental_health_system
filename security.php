<?php
// security.php - Security Logs for Admins
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$user_name = $_SESSION['name'];
include '../config/config.php';

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="security_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Time', 'User Name', 'User Email', 'Event Type', 'Description', 'IP Address', 'User Agent']);

    // Get filtered data for export
    $export_query = "SELECT sl.created_at, u.name, u.email, sl.event_type, sl.event_description, sl.ip_address, sl.user_agent FROM security_logs sl LEFT JOIN users u ON sl.user_id = u.id WHERE 1=1";
    $export_params = [];
    $export_types = "";

    if ($filter_type !== 'all') {
        $export_query .= " AND sl.event_type=?";
        $export_params[] = $filter_type;
        $export_types .= "s";
    }

    if (!empty($filter_user)) {
        $export_query .= " AND (sl.event_description LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $search = "%{$filter_user}%";
        $export_params[] = $search;
        $export_params[] = $search;
        $export_params[] = $search;
        $export_types .= "sss";
    }

    $export_query .= " AND sl.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY sl.created_at DESC";
    $export_params[] = $days;
    $export_types .= "i";

    $stmt = $conn->prepare($export_query);
    if (!empty($export_params)) {
        $stmt->bind_param($export_types, ...$export_params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['created_at'],
                $row['name'] ?? 'Unknown',
                $row['email'] ?? '',
                $row['event_type'],
                $row['event_description'],
                $row['ip_address'],
                $row['user_agent']
            ]);
        }
    }
    fclose($output);
    exit();
}

$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_user = isset($_GET['user']) ? trim($_GET['user']) : '';
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

// Build query with user information
$query = "SELECT sl.id, sl.user_id, u.name as user_name, u.email as user_email, sl.event_type, sl.event_description, sl.ip_address, sl.user_agent, sl.created_at FROM security_logs sl LEFT JOIN users u ON sl.user_id = u.id WHERE 1=1";
$params = [];
$types = "";

if ($filter_type !== 'all') {
    $query .= " AND sl.event_type=?";
    $params[] = $filter_type;
    $types .= "s";
}

if (!empty($filter_user)) {
    $query .= " AND (sl.event_description LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $search = "%{$filter_user}%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

$query .= " AND sl.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$params[] = $days;
$types .= "i";

$query .= " ORDER BY sl.created_at DESC LIMIT 500";

$logs = [];
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}
$stmt->close();

// Get statistics
$stats = [];

// Total login attempts (last 30 days)
$result = $conn->query("SELECT COUNT(*) as count FROM security_logs WHERE event_type='login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$row = $result->fetch_assoc();
$stats['total_logins'] = $row['count'];

// Failed login attempts (last 30 days)
$result = $conn->query("SELECT COUNT(*) as count FROM security_logs WHERE event_type='failed_login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$row = $result->fetch_assoc();
$stats['failed_logins'] = $row['count'];

// Admin actions (last 30 days)
$result = $conn->query("SELECT COUNT(*) as count FROM security_logs WHERE event_type='admin_action' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$row = $result->fetch_assoc();
$stats['admin_actions'] = $row['count'];

// Logout events (last 30 days)
$result = $conn->query("SELECT COUNT(*) as count FROM security_logs WHERE event_type='logout' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$row = $result->fetch_assoc();
$stats['logout_events'] = $row['count'];

// Unique users (last 30 days)
$result = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$row = $result->fetch_assoc();
$stats['unique_users'] = $row['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-box { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 4px solid #667eea; }
        .stat-box.warning { border-left-color: #ff9800; }
        .stat-box.danger { border-left-color: #dc3545; }
        .stat-box h4 { color: #666; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .stat-box .value { font-size: 2rem; font-weight: 700; color: #333; }
        .filter-section { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        .log-row { padding: 1rem; border-bottom: 1px solid #eee; display: grid; grid-template-columns: 120px 1fr 200px 150px; gap: 1rem; align-items: center; }
        .log-row:last-child { border-bottom: none; }
        .event-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .event-login { background-color: #d4edda; color: #155724; }
        .event-failed { background-color: #f8d7da; color: #721c24; }
        .event-admin { background-color: #cce5ff; color: #004085; }
        .event-logout { background-color: #fff3cd; color: #856404; }
        .text-muted { color: #6c757d; }
        .btn-export { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-export:hover { background-color: #218838; }
        .page-actions { display: flex; gap: 15px; align-items: center; margin-top: 10px; }
        .btn-refresh { background-color: #17a2b8; color: white; padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .btn-refresh:hover { background-color: #138496; }
        .auto-refresh { display: flex; align-items: center; gap: 5px; font-size: 0.9rem; }
        @media (max-width: 768px) {
            .log-row { grid-template-columns: 1fr; gap: 0.5rem; }
            .page-actions { flex-direction: column; align-items: stretch; }
            .btn-refresh, .auto-refresh { justify-content: center; }
            .filter-row { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <h1><i class="fas fa-shield-alt"></i> Admin Panel</h1>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($user_name); ?></span>
            <span class="user-role">Administrator</span>
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <a href="../admin/admin_dashboard.php" class="btn-secondary">Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Security Logs</h2>
            <p>Monitor user activity, login attempts, and admin actions</p>
            <div class="page-actions">
                <button onclick="location.reload()" class="btn-refresh">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <label class="auto-refresh">
                    <input type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh()"> Auto-refresh (30s)
                </label>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-box">
                <h4><i class="fas fa-sign-in-alt"></i> Successful Logins (30d)</h4>
                <div class="value"><?php echo $stats['total_logins']; ?></div>
            </div>
            <div class="stat-box danger">
                <h4><i class="fas fa-exclamation-triangle"></i> Failed Logins (30d)</h4>
                <div class="value"><?php echo $stats['failed_logins']; ?></div>
            </div>
            <div class="stat-box warning">
                <h4><i class="fas fa-cog"></i> Admin Actions (30d)</h4>
                <div class="value"><?php echo $stats['admin_actions']; ?></div>
            </div>
            <div class="stat-box">
                <h4><i class="fas fa-sign-out-alt"></i> Logouts (30d)</h4>
                <div class="value"><?php echo $stats['logout_events']; ?></div>
            </div>
            <div class="stat-box">
                <h4><i class="fas fa-users"></i> Active Users (30d)</h4>
                <div class="value"><?php echo $stats['unique_users']; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form action="" method="GET" class="filter-row">
                <div class="form-group">
                    <label for="type">Event Type</label>
                    <select id="type" name="type">
                        <option value="all" <?php echo ($filter_type === 'all') ? 'selected' : ''; ?>>All Events</option>
                        <option value="login" <?php echo ($filter_type === 'login') ? 'selected' : ''; ?>>Successful Logins</option>
                        <option value="failed_login" <?php echo ($filter_type === 'failed_login') ? 'selected' : ''; ?>>Failed Logins</option>
                        <option value="logout" <?php echo ($filter_type === 'logout') ? 'selected' : ''; ?>>Logouts</option>
                        <option value="admin_action" <?php echo ($filter_type === 'admin_action') ? 'selected' : ''; ?>>Admin Actions</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="days">Time Period</label>
                    <select id="days" name="days">
                        <option value="1" <?php echo ($days === 1) ? 'selected' : ''; ?>>Last 24 Hours</option>
                        <option value="7" <?php echo ($days === 7) ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo ($days === 30) ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90" <?php echo ($days === 90) ? 'selected' : ''; ?>>Last 90 Days</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="user">Search</label>
                    <input type="text" id="user" name="user" placeholder="Search user, email, or description..." value="<?php echo htmlspecialchars($filter_user); ?>">
                </div>

                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>

                <a href="?export=csv&type=<?php echo $filter_type; ?>&days=<?php echo $days; ?>&user=<?php echo urlencode($filter_user); ?>" class="btn-export">
                    <i class="fas fa-download"></i> Export CSV
                </a>
            </form>
        </div>

        <!-- Logs Table -->
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <h3>No Security Events Found</h3>
                <p>No activity matching your filters.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Event</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($log['user_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($log['user_email']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = 'event-login';
                                    $icon = 'fa-check-circle';
                                    if ($log['event_type'] === 'failed_login') {
                                        $badge_class = 'event-failed';
                                        $icon = 'fa-times-circle';
                                    } elseif ($log['event_type'] === 'admin_action') {
                                        $badge_class = 'event-admin';
                                        $icon = 'fa-cog';
                                    } elseif ($log['event_type'] === 'logout') {
                                        $badge_class = 'event-logout';
                                        $icon = 'fa-sign-out-alt';
                                    }
                                    ?>
                                    <span class="event-badge <?php echo $badge_class; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i> <?php echo ucfirst(str_replace('_', ' ', $log['event_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['event_description']); ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let autoRefreshInterval;

        function toggleAutoRefresh() {
            const checkbox = document.getElementById('autoRefresh');
            if (checkbox.checked) {
                autoRefreshInterval = setInterval(() => {
                    location.reload();
                }, 30000); // 30 seconds
            } else {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                }
            }
        }

        // Check if auto-refresh was previously enabled
        if (localStorage.getItem('securityLogsAutoRefresh') === 'true') {
            document.getElementById('autoRefresh').checked = true;
            toggleAutoRefresh();
        }

        // Save auto-refresh preference
        document.getElementById('autoRefresh').addEventListener('change', function() {
            localStorage.setItem('securityLogsAutoRefresh', this.checked);
        });
    </script>

</body>
</html>