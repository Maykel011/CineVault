<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Handle movie deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $deleteQuery = "DELETE FROM content WHERE id = ? AND content_type = 'movie'";
    $stmt = $db->prepare($deleteQuery);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Movie deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Error deleting movie!";
        $messageType = "error";
    }
}

// Handle featured/toggle
if (isset($_GET['toggle_featured'])) {
    $id = (int)$_GET['toggle_featured'];
    $updateQuery = "UPDATE content SET featured = NOT featured WHERE id = ?";
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: movies.php');
    exit();
}

// Handle trending toggle
if (isset($_GET['toggle_trending'])) {
    $id = (int)$_GET['toggle_trending'];
    $updateQuery = "UPDATE content SET trending = NOT trending WHERE id = ?";
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: movies.php');
    exit();
}

// Get all movies with details
$moviesQuery = "SELECT c.*, g.name as genre_name, 
                (SELECT COUNT(*) FROM video_sources WHERE content_id = c.id) as quality_count
                FROM content c
                LEFT JOIN genres g ON c.genre_id = g.id
                WHERE c.content_type = 'movie'
                ORDER BY c.created_at DESC";
$movies = $db->query($moviesQuery);

// Get statistics
$statsQuery = "SELECT 
                COUNT(*) as total_movies,
                SUM(featured = 1) as featured_count,
                SUM(trending = 1) as trending_count,
                AVG(rating) as avg_rating
                FROM content WHERE content_type = 'movie'";
$stats = $db->query($statsQuery)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies Management - CineVault Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="css/movies.css">
</head>
<body>
    <div class="admin-container">

        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>CineVault</h1>
                <p>Admin Panel</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="content.php">🎬 All Content</a>
                <a href="movies.php" class="active">🎥 Movies</a>
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
                <div>
                    <h2>Movies Management</h2>
                    <p class="subtitle">Manage your movie catalog</p>
                </div>
                <div class="header-actions">
                    <a href="add-movie.php" class="btn-primary">+ Add New Movie</a>
                    <a href="export.php?type=movies" class="btn-secondary">📥 Export</a>
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
                    <div class="stat-icon">🎥</div>
                    <div class="stat-details">
                        <h3>Total Movies</h3>
                        <p class="stat-number"><?php echo $stats['total_movies'] ?? 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-details">
                        <h3>Featured</h3>
                        <p class="stat-number"><?php echo $stats['featured_count'] ?? 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-details">
                        <h3>Trending</h3>
                        <p class="stat-number"><?php echo $stats['trending_count'] ?? 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-details">
                        <h3>Avg Rating</h3>
                        <p class="stat-number"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search movies...">
                </div>
                <div class="filter-options">
                    <select id="genreFilter">
                        <option value="">All Genres</option>
                        <?php
                        $genres = $db->query("SELECT * FROM genres ORDER BY name");
                        while ($genre = $genres->fetch_assoc()) {
                            echo "<option value='{$genre['id']}'>{$genre['name']}</option>";
                        }
                        ?>
                    </select>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="featured">Featured</option>
                        <option value="trending">Trending</option>
                    </select>
                    <select id="yearFilter">
                        <option value="">All Years</option>
                        <?php for ($year = date('Y'); $year >= 1990; $year--): ?>
                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <!-- Movies Table -->
            <div class="table-responsive">
                <table class="data-table" id="moviesTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Poster</th>
                            <th>Title</th>
                            <th>Genre</th>
                            <th>Year</th>
                            <th>Rating</th>
                            <th>Qualities</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($movies->num_rows > 0): ?>
                            <?php while ($movie = $movies->fetch_assoc()): ?>
                            <tr>
                                <td><input type="checkbox" class="select-item" value="<?php echo $movie['id']; ?>"></td>
                                <td>#<?php echo $movie['id']; ?></td>
                                <td>
                                    <?php if ($movie['poster_image']): ?>
                                        <img src="../<?php echo $movie['poster_image']; ?>" alt="" class="table-thumb">
                                    <?php else: ?>
                                        <div class="no-poster">🎬</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($movie['title']); ?></strong>
                                    <small><?php echo substr($movie['description'], 0, 50); ?>...</small>
                                </td>
                                <td><?php echo $movie['genre_name'] ?? 'Uncategorized'; ?></td>
                                <td><?php echo $movie['release_year']; ?></td>
                                <td>
                                    <span class="rating-badge">⭐ <?php echo $movie['rating'] ?? 'N/A'; ?></span>
                                </td>
                                <td>
                                    <?php if ($movie['quality_count'] > 0): ?>
                                        <span class="quality-dots">
                                            <?php
                                            $qualities = $db->query("SELECT quality FROM video_sources WHERE content_id = {$movie['id']}");
                                            while ($q = $qualities->fetch_assoc()) {
                                                $color = $q['quality'] == '4K' ? '#gold' : ($q['quality'] == '1080p' ? '#4caf50' : '#2196f3');
                                                echo "<span class='quality-dot' style='background: $color' title='{$q['quality']}'></span>";
                                            }
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge warning">No video</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($movie['featured']): ?>
                                        <span class="badge featured">Featured</span>
                                    <?php endif; ?>
                                    <?php if ($movie['trending']): ?>
                                        <span class="badge trending">Trending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="view-movie.php?id=<?php echo $movie['id']; ?>" class="btn-icon" title="View">👁️</a>
                                    <a href="edit-movie.php?id=<?php echo $movie['id']; ?>" class="btn-icon" title="Edit">✏️</a>
                                    <a href="?toggle_featured=<?php echo $movie['id']; ?>" class="btn-icon" title="Toggle Featured">⭐</a>
                                    <a href="?toggle_trending=<?php echo $movie['id']; ?>" class="btn-icon" title="Toggle Trending">📈</a>
                                    <a href="video-sources.php?content_id=<?php echo $movie['id']; ?>" class="btn-icon" title="Video Sources">🎬</a>
                                    <a href="?delete=<?php echo $movie['id']; ?>" class="btn-icon delete" onclick="return confirm('Are you sure?')" title="Delete">🗑️</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="empty-table">
                                    <div class="empty-state">
                                        <span class="empty-icon">🎥</span>
                                        <h3>No Movies Found</h3>
                                        <p>Start by adding your first movie</p>
                                        <a href="add-movie.php" class="btn-primary">Add Movie</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <select id="bulkAction">
                    <option value="">Bulk Actions</option>
                    <option value="featured">Mark as Featured</option>
                    <option value="trending">Mark as Trending</option>
                    <option value="delete">Delete Selected</option>
                    <option value="export">Export Selected</option>
                </select>
                <button onclick="bulkAction()" class="btn-secondary">Apply</button>
            </div>
        </main>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('genreFilter').addEventListener('change', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        document.getElementById('yearFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const genre = document.getElementById('genreFilter').value;
            const status = document.getElementById('statusFilter').value;
            const year = document.getElementById('yearFilter').value;
            
            const rows = document.querySelectorAll('#moviesTable tbody tr');
            
            rows.forEach(row => {
                let show = true;
                
                if (search) {
                    const title = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                    if (!title.includes(search)) show = false;
                }
                
                if (genre && show) {
                    const rowGenre = row.querySelector('td:nth-child(5)').textContent;
                    if (!rowGenre.includes(genre)) show = false;
                }
                
                if (year && show) {
                    const rowYear = row.querySelector('td:nth-child(6)').textContent;
                    if (rowYear !== year) show = false;
                }
                
                if (status && show) {
                    const statusCell = row.querySelector('td:nth-child(9)').textContent;
                    if (status === 'featured' && !statusCell.includes('Featured')) show = false;
                    if (status === 'trending' && !statusCell.includes('Trending')) show = false;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.select-item');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        
        // Bulk actions
        function bulkAction() {
            const action = document.getElementById('bulkAction').value;
            const selected = Array.from(document.querySelectorAll('.select-item:checked')).map(cb => cb.value);
            
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            if (selected.length === 0) {
                alert('Please select items');
                return;
            }
            
            if (action === 'delete' && !confirm(`Delete ${selected.length} movies?`)) {
                return;
            }
            
            // Send bulk action via AJAX
            fetch('api/bulk-movie-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    ids: selected
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        // Close alerts
        document.querySelectorAll('.close-alert').forEach(btn => {
            btn.addEventListener('click', function() {
                this.parentElement.remove();
            });
        });
    </script>
    
</body>
</html>