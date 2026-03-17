<?php
// resources.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
$user_name = $_SESSION['name'];
$user_id = (int)$_SESSION['user_id'];

// Include database connection
include '../config/config.php';

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

// Build query
$query = "SELECT * FROM resources WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($category) && $category !== 'all') {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Sorting
switch ($sort) {
    case 'popular':
        $query .= " ORDER BY id DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY created_at ASC";
        break;
    default:
        $query .= " ORDER BY created_at DESC";
}

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$resources = [];
while ($row = $result->fetch_assoc()) {
    $resources[] = $row;
}
$stmt->close();

// Get resource statistics
$total_resources = count($resources);
$categories = [
    'all' => 'All',
    'article' => 'Articles',
    'video' => 'Videos',
    'exercise' => 'Exercises',
    'meditation' => 'Meditation'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - Mental Health System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">

</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-book-open"></i> Resources</h1>
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
            <h2>Mental Health Resources</h2>
            <p>Access helpful articles, videos, exercises, and meditation guides</p>
        </div>

        <!-- Search and Filter -->
        <div class="filter-section">
            <form action="" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Search Resources</label>
                    <input type="text" id="search" name="search" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="all" <?php echo ($category === 'all' || empty($category)) ? 'selected' : ''; ?>>All Categories</option>
                        <option value="article" <?php echo ($category === 'article') ? 'selected' : ''; ?>>Articles</option>
                        <option value="video" <?php echo ($category === 'video') ? 'selected' : ''; ?>>Videos</option>
                        <option value="exercise" <?php echo ($category === 'exercise') ? 'selected' : ''; ?>>Exercises</option>
                        <option value="meditation" <?php echo ($category === 'meditation') ? 'selected' : ''; ?>>Meditation</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sort">Sort By</label>
                    <select id="sort" name="sort">
                        <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="popular" <?php echo ($sort === 'popular') ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                </div>

                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <!-- Category Filter Buttons -->
        <div class="category-filter">
            <a href="resources.php" class="category-btn <?php echo ($category === 'all' || empty($category)) ? 'active' : ''; ?>">
                All
            </a>
            <a href="resources.php?category=article" class="category-btn <?php echo ($category === 'article') ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Articles
            </a>
            <a href="resources.php?category=video" class="category-btn <?php echo ($category === 'video') ? 'active' : ''; ?>">
                <i class="fas fa-video"></i> Videos
            </a>
            <a href="resources.php?category=exercise" class="category-btn <?php echo ($category === 'exercise') ? 'active' : ''; ?>">
                <i class="fas fa-dumbbell"></i> Exercises
            </a>
            <a href="resources.php?category=meditation" class="category-btn <?php echo ($category === 'meditation') ? 'active' : ''; ?>">
                <i class="fas fa-spa"></i> Meditation
            </a>
        </div>

        <!-- Resources Grid -->
        <?php if (empty($resources)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No Resources Found</h3>
                <p>Try adjusting your search or filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="resources-grid">
                <?php foreach ($resources as $resource): ?>
                    <div class="resource-card">
                        <div class="resource-icon <?php echo $resource['category']; ?>">
                            <?php
                            $icons = [
                                'article' => '📄',
                                'video' => '🎥',
                                'exercise' => '💪',
                                'meditation' => '🧘'
                            ];
                            echo $icons[$resource['category']] ?? '📚';
                            ?>
                        </div>
                        <div class="resource-content">
                            <h3 class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></h3>
                            <p class="resource-description"><?php echo htmlspecialchars(substr($resource['description'], 0, 100)); ?><?php echo strlen($resource['description']) > 100 ? '...' : ''; ?></p>
                            <div class="resource-meta">
                                <span class="resource-date">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['created_at'])); ?>
                                </span>
                                <a href="resource_detail.php?id=<?php echo $resource['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

                </div>