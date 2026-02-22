<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Handle series deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $deleteQuery = "DELETE FROM content WHERE id = ? AND content_type = 'series'";
    $stmt = $db->prepare($deleteQuery);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Series deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Error deleting series!";
        $messageType = "error";
    }
}

// Get all series with details
$seriesQuery = "SELECT c.*, g.name as genre_name,
                (SELECT COUNT(*) FROM seasons WHERE series_id = c.id) as seasons_count,
                (SELECT COUNT(*) FROM episodes e 
                 JOIN seasons s ON e.season_id = s.id 
                 WHERE s.series_id = c.id) as episodes_count
                FROM content c
                LEFT JOIN genres g ON c.genre_id = g.id
                WHERE c.content_type = 'series'
                ORDER BY c.created_at DESC";
$series = $db->query($seriesQuery);

// Get statistics
$statsQuery = "SELECT 
                COUNT(*) as total_series,
                SUM(featured = 1) as featured_count,
                SUM(trending = 1) as trending_count,
                AVG(rating) as avg_rating
                FROM content WHERE content_type = 'series'";
$stats = $db->query($statsQuery)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Series Management - CineVault Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="css/series.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>CineVault</h1>
                <p>Admin Panel</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="content.php">🎬 All Content</a>
                <a href="movies.php">🎥 Movies</a>
                <a href="series.php" class="active">📺 Series</a>
                <a href="genres.php">🏷️ Genres</a>
                <a href="users.php">👥 Users</a>
                <a href="admins.php">👤 Administrators</a>
                <a href="settings.php">⚙️ Settings</a>
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
                <div>
                    <h2>TV Series Management</h2>
                    <p class="subtitle">Manage your series and episodes</p>
                </div>
                <div class="header-actions">
                    <a href="add-series.php" class="btn-primary">+ Add New Series</a>
                    <a href="export.php?type=series" class="btn-secondary">📥 Export</a>
                </div>
            </header>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                    <span class="close-alert">&times;</span>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📺</div>
                    <div class="stat-details">
                        <h3>Total Series</h3>
                        <p class="stat-number"><?php echo $stats['total_series'] ?? 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-details">
                        <h3>Total Seasons</h3>
                        <p class="stat-number"><?php
                            $seasons = $db->query("SELECT COUNT(*) as count FROM seasons");
                            echo $seasons->fetch_assoc()['count'];
                        ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🎬</div>
                    <div class="stat-details">
                        <h3>Total Episodes</h3>
                        <p class="stat-number"><?php
                            $episodes = $db->query("SELECT COUNT(*) as count FROM episodes");
                            echo $episodes->fetch_assoc()['count'];
                        ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-details">
                        <h3>Avg Rating</h3>
                        <p class="stat-number"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Series Grid View -->
            <div class="view-toggle">
                <button class="view-btn active" onclick="toggleView('grid')">📱 Grid View</button>
                <button class="view-btn" onclick="toggleView('list')">📋 List View</button>
            </div>
            
            <!-- Series Grid -->
            <div id="gridView" class="series-grid">
                <?php if ($series->num_rows > 0): ?>
                    <?php while ($show = $series->fetch_assoc()): ?>
                    <div class="series-card">
                        <div class="series-poster">
                            <?php if ($show['poster_image']): ?>
                                <img src="../<?php echo $show['poster_image']; ?>" alt="">
                            <?php else: ?>
                                <div class="poster-placeholder">📺</div>
                            <?php endif; ?>
                            
                            <div class="series-overlay">
                                <a href="edit-series.php?id=<?php echo $show['id']; ?>" class="overlay-btn">Edit</a>
                                <a href="manage-seasons.php?id=<?php echo $show['id']; ?>" class="overlay-btn">Seasons</a>
                            </div>
                        </div>
                        
                        <div class="series-info">
                            <h3><?php echo htmlspecialchars($show['title']); ?></h3>
                            <div class="series-meta">
                                <span class="meta-item">📅 <?php echo $show['release_year']; ?></span>
                                <span class="meta-item">📺 <?php echo $show['seasons_count']; ?> Seasons</span>
                                <span class="meta-item">🎬 <?php echo $show['episodes_count']; ?> Episodes</span>
                            </div>
                            <div class="series-genre"><?php echo $show['genre_name'] ?? 'Uncategorized'; ?></div>
                            <div class="series-rating">⭐ <?php echo $show['rating'] ?? 'N/A'; ?></div>
                            <div class="series-status">
                                <?php if ($show['featured']): ?>
                                    <span class="badge featured">Featured</span>
                                <?php endif; ?>
                                <?php if ($show['trending']): ?>
                                    <span class="badge trending">Trending</span>
                                <?php endif; ?>
                            </div>
                            <div class="series-actions">
                                <a href="?toggle_featured=<?php echo $show['id']; ?>" class="action-link">⭐</a>
                                <a href="?toggle_trending=<?php echo $show['id']; ?>" class="action-link">📈</a>
                                <a href="?delete=<?php echo $show['id']; ?>" class="action-link delete" onclick="return confirm('Delete this series?')">🗑️</a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">📺</span>
                        <h3>No Series Found</h3>
                        <p>Start by adding your first TV series</p>
                        <a href="add-series.php" class="btn-primary">Add Series</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- List View (Hidden by default) -->
            <div id="listView" class="table-responsive" style="display: none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Genre</th>
                            <th>Seasons</th>
                            <th>Episodes</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $series->data_seek(0); // Reset pointer
                        while ($show = $series->fetch_assoc()): 
                        ?>
                        <tr>
                            <td>#<?php echo $show['id']; ?></td>
                            <td><?php echo htmlspecialchars($show['title']); ?></td>
                            <td><?php echo $show['genre_name'] ?? 'N/A'; ?></td>
                            <td><?php echo $show['seasons_count']; ?></td>
                            <td><?php echo $show['episodes_count']; ?></td>
                            <td>⭐ <?php echo $show['rating'] ?? 'N/A'; ?></td>
                            <td>
                                <?php if ($show['featured']): ?>⭐<?php endif; ?>
                                <?php if ($show['trending']): ?>📈<?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="edit-series.php?id=<?php echo $show['id']; ?>">✏️</a>
                                <a href="manage-seasons.php?id=<?php echo $show['id']; ?>">📺</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
        function toggleView(view) {
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const btns = document.querySelectorAll('.view-btn');
            
            if (view === 'grid') {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
                btns[0].classList.add('active');
                btns[1].classList.remove('active');
            } else {
                gridView.style.display = 'none';
                listView.style.display = 'block';
                btns[0].classList.remove('active');
                btns[1].classList.add('active');
            }
        }
    </script>
    
    
</body>
</html>