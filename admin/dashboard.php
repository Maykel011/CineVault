<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Get stats
$stats = [];

// Total content
$result = $db->query("SELECT 
    COUNT(*) as total,
    SUM(content_type = 'movie') as movies,
    SUM(content_type = 'series') as series
    FROM content");
$stats['content'] = $result->fetch_assoc();

// Total users
$result = $db->query("SELECT COUNT(*) as total FROM users");
$stats['users'] = $result->fetch_assoc()['total'];

// Recent content
$recentContent = $db->query("
    SELECT c.*, g.name as genre_name, a.username as created_by_name
    FROM content c
    LEFT JOIN genres g ON c.genre_id = g.id
    LEFT JOIN admin_users a ON c.created_by = a.id
    ORDER BY c.created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CineVault</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>CineVault</h1>
                <p>Admin Panel</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">
                    <span>📊</span> Dashboard
                </a>
                <a href="content.php">
                    <span>🎬</span> Content
                </a>
                <a href="movies.php">
                    <span>🎥</span> Movies
                </a>
                <a href="series.php">
                    <span>📺</span> Series
                </a>
                <a href="genres.php">
                    <span>🏷️</span> Genres
                </a>
                <a href="users.php">
                    <span>👥</span> Users
                </a>
                <a href="admins.php">
                    <span>👤</span> Administrators
                </a>
                <a href="settings.php">
                    <span>⚙️</span> Settings
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="admin-info">
                    <strong><?php echo $_SESSION['admin_username']; ?></strong>
                    <span><?php echo $_SESSION['admin_role']; ?></span>
                </div>
                <a href="api/logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h2>Dashboard</h2>
                <div class="header-actions">
                    <span class="date"><?php echo date('F j, Y'); ?></span>
                </div>
            </header>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🎬</div>
                    <div class="stat-details">
                        <h3>Total Content</h3>
                        <p class="stat-number"><?php echo $stats['content']['total'] ?? 0; ?></p>
                        <div class="stat-breakdown">
                            <span>Movies: <?php echo $stats['content']['movies'] ?? 0; ?></span>
                            <span>Series: <?php echo $stats['content']['series'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-details">
                        <h3>Total Users</h3>
                        <p class="stat-number"><?php echo $stats['users']; ?></p>
                        <div class="stat-breakdown">
                            <span>Active: <?php echo $stats['users']; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📺</div>
                    <div class="stat-details">
                        <h3>Total Views</h3>
                        <p class="stat-number">0</p>
                        <div class="stat-breakdown">
                            <span>Today: 0</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-details">
                        <h3>Avg Rating</h3>
                        <p class="stat-number">0.0</p>
                        <div class="stat-breakdown">
                            <span>Total Reviews: 0</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <a href="add-movie.php" class="action-btn">
                        <span>🎥</span> Add Movie
                    </a>
                    <a href="add-series.php" class="action-btn">
                        <span>📺</span> Add Series
                    </a>
                    <a href="add-genre.php" class="action-btn">
                        <span>🏷️</span> Add Genre
                    </a>
                    <a href="add-admin.php" class="action-btn">
                        <span>👤</span> Add Admin
                    </a>
                </div>
            </div>
            
            <!-- Recent Content -->
            <div class="recent-content">
                <h3>Recent Content</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Genre</th>
                            <th>Created By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $recentContent->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                            <td><span class="badge <?php echo $item['content_type']; ?>"><?php echo $item['content_type']; ?></span></td>
                            <td><?php echo $item['genre_name'] ?? 'N/A'; ?></td>
                            <td><?php echo $item['created_by_name'] ?? 'N/A'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                            <td>
                                <a href="edit-content.php?id=<?php echo $item['id']; ?>" class="btn-small">Edit</a>
                                <a href="delete-content.php?id=<?php echo $item['id']; ?>" class="btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script src="assets/admin.js"></script>
</body>
</html>