<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Get all content with details
$content = $db->query("
    SELECT c.*, g.name as genre_name, a.username as created_by_name,
    (SELECT COUNT(*) FROM seasons WHERE series_id = c.id) as season_count
    FROM content c
    LEFT JOIN genres g ON c.genre_id = g.id
    LEFT JOIN admin_users a ON c.created_by = a.id
    ORDER BY c.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - CineVault Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar (same as dashboard) -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>CineVault</h1>
                <p>Admin Panel</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="content.php" class="active">🎬 Content</a>
                <a href="movies.php">🎥 Movies</a>
                <a href="series.php">📺 Series</a>
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
                <h2>Content Management</h2>
                <div class="header-actions">
                    <a href="add-content.php" class="btn-primary">+ Add New Content</a>
                </div>
            </header>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search content...">
                </div>
                <div class="filter-options">
                    <select id="typeFilter">
                        <option value="">All Types</option>
                        <option value="movie">Movies</option>
                        <option value="series">Series</option>
                    </select>
                    <select id="genreFilter">
                        <option value="">All Genres</option>
                        <?php
                        $genres = $db->query("SELECT * FROM genres ORDER BY name");
                        while ($genre = $genres->fetch_assoc()) {
                            echo "<option value='{$genre['id']}'>{$genre['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <!-- Content Table -->
            <div class="table-responsive">
                <table class="data-table" id="contentTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Genre</th>
                            <th>Year</th>
                            <th>Rating</th>
                            <th>Seasons</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $content->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td>
                                <div class="title-cell">
                                    <?php if ($item['poster_image']): ?>
                                        <img src="../<?php echo $item['poster_image']; ?>" alt="" class="table-thumb">
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($item['title']); ?></span>
                                </div>
                            </td>
                            <td><span class="badge <?php echo $item['content_type']; ?>"><?php echo $item['content_type']; ?></span></td>
                            <td><?php echo $item['genre_name'] ?? 'N/A'; ?></td>
                            <td><?php echo $item['release_year']; ?></td>
                            <td><?php echo $item['rating'] ?? 'N/A'; ?></td>
                            <td><?php echo $item['content_type'] == 'series' ? $item['season_count'] : '-'; ?></td>
                            <td>
                                <?php if ($item['featured']): ?>
                                    <span class="badge featured">Featured</span>
                                <?php endif; ?>
                                <?php if ($item['trending']): ?>
                                    <span class="badge trending">Trending</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="edit-content.php?id=<?php echo $item['id']; ?>" class="btn-icon" title="Edit">✏️</a>
                                <a href="manage-seasons.php?id=<?php echo $item['id']; ?>" class="btn-icon" title="Manage Seasons">📺</a>
                                <a href="manage-episodes.php?id=<?php echo $item['id']; ?>" class="btn-icon" title="Manage Episodes">🎬</a>
                                <a href="api/delete-content.php?id=<?php echo $item['id']; ?>" class="btn-icon delete" onclick="return confirm('Are you sure you want to delete this content?')" title="Delete">🗑️</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#contentTable tbody tr');
            
            tableRows.forEach(row => {
                const title = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                if (title.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Filter by type
        document.getElementById('typeFilter').addEventListener('change', filterTable);
        document.getElementById('genreFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const type = document.getElementById('typeFilter').value;
            const genre = document.getElementById('genreFilter').value;
            const tableRows = document.querySelectorAll('#contentTable tbody tr');
            
            tableRows.forEach(row => {
                let show = true;
                
                if (type) {
                    const rowType = row.querySelector('td:nth-child(3) .badge').textContent.toLowerCase();
                    if (rowType !== type) show = false;
                }
                
                if (genre && show) {
                    const rowGenre = row.querySelector('td:nth-child(4)').textContent;
                    if (rowGenre !== document.getElementById('genreFilter').selectedOptions[0].text) show = false;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
    </script>
</body>
</html>