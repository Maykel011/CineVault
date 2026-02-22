<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Handle Add Genre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_genre'])) {
    $name = $_POST['name'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $description = $_POST['description'];
    
    $insertQuery = "INSERT INTO genres (name, slug, description) VALUES (?, ?, ?)";
    $stmt = $db->prepare($insertQuery);
    $stmt->bind_param("sss", $name, $slug, $description);
    
    if ($stmt->execute()) {
        $message = "Genre added successfully!";
        $messageType = "success";
    } else {
        $message = "Error adding genre: " . $db->error;
        $messageType = "error";
    }
}

// Handle Edit Genre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_genre'])) {
    $id = $_POST['genre_id'];
    $name = $_POST['name'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $description = $_POST['description'];
    
    $updateQuery = "UPDATE genres SET name = ?, slug = ?, description = ? WHERE id = ?";
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param("sssi", $name, $slug, $description, $id);
    
    if ($stmt->execute()) {
        $message = "Genre updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating genre!";
        $messageType = "error";
    }
}

// Handle Delete Genre
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if genre is being used
    $checkQuery = "SELECT COUNT(*) as count FROM content WHERE genre_id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $used = $result->fetch_assoc()['count'];
    
    if ($used > 0) {
        $message = "Cannot delete genre - it's being used by $used content items!";
        $messageType = "error";
    } else {
        $deleteQuery = "DELETE FROM genres WHERE id = ?";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $id);
        
        if ($deleteStmt->execute()) {
            $message = "Genre deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error deleting genre!";
            $messageType = "error";
        }
    }
}

// Get all genres with content count
$genresQuery = "SELECT g.*, 
                COUNT(c.id) as content_count,
                SUM(c.content_type = 'movie') as movie_count,
                SUM(c.content_type = 'series') as series_count
                FROM genres g
                LEFT JOIN content c ON g.id = c.genre_id
                GROUP BY g.id
                ORDER BY g.name";
$genres = $db->query($genresQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Genres Management - CineVault Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="css/genre.css">
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
                <a href="series.php">📺 Series</a>
                <a href="genres.php" class="active">🏷️ Genres</a>
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
                    <h2>Genres Management</h2>
                    <p class="subtitle">Organize your content by categories</p>
                </div>
                <button class="btn-primary" onclick="openAddModal()">+ Add New Genre</button>
            </header>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                    <span class="close-alert">&times;</span>
                </div>
            <?php endif; ?>
            
            <!-- Genre Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🏷️</div>
                    <div class="stat-details">
                        <h3>Total Genres</h3>
                        <p class="stat-number"><?php echo $genres->num_rows; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🎬</div>
                    <div class="stat-details">
                        <h3>Active Genres</h3>
                        <p class="stat-number"><?php
                            $active = $db->query("SELECT COUNT(DISTINCT genre_id) as count FROM content WHERE genre_id IS NOT NULL");
                            echo $active->fetch_assoc()['count'];
                        ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-details">
                        <h3>Most Used</h3>
                        <p class="stat-number"><?php
                            $most = $db->query("SELECT g.name FROM genres g 
                                               JOIN content c ON g.id = c.genre_id 
                                               GROUP BY g.id 
                                               ORDER BY COUNT(c.id) DESC 
                                               LIMIT 1");
                            echo $most->num_rows > 0 ? $most->fetch_assoc()['name'] : 'N/A';
                        ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-details">
                        <h3>Unused Genres</h3>
                        <p class="stat-number"><?php
                            $unused = $db->query("SELECT COUNT(*) as count FROM genres g 
                                                 LEFT JOIN content c ON g.id = c.genre_id 
                                                 WHERE c.id IS NULL");
                            echo $unused->fetch_assoc()['count'];
                        ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Genres Grid -->
            <div class="genres-grid">
                <?php while ($genre = $genres->fetch_assoc()): ?>
                <div class="genre-card">
                    <div class="genre-header">
                        <h3><?php echo htmlspecialchars($genre['name']); ?></h3>
                        <div class="genre-actions">
                            <button onclick="editGenre(<?php echo $genre['id']; ?>)" class="icon-btn">✏️</button>
                            <a href="?delete=<?php echo $genre['id']; ?>" class="icon-btn delete" onclick="return confirm('Delete this genre?')">🗑️</a>
                        </div>
                    </div>
                    
                    <div class="genre-slug"><?php echo $genre['slug']; ?></div>
                    <p class="genre-description"><?php echo $genre['description'] ?? 'No description'; ?></p>
                    
                    <div class="genre-stats">
                        <div class="stat-item">
                            <span class="stat-label">Total Content</span>
                            <span class="stat-value"><?php echo $genre['content_count']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Movies</span>
                            <span class="stat-value"><?php echo $genre['movie_count']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Series</span>
                            <span class="stat-value"><?php echo $genre['series_count']; ?></span>
                        </div>
                    </div>
                    
                    <div class="genre-preview">
                        <a href="../pages/genre.php?slug=<?php echo $genre['slug']; ?>" target="_blank" class="preview-link">View on Site →</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Add Genre Modal -->
            <div id="addModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Add New Genre</h3>
                        <span class="close-modal">&times;</span>
                    </div>
                    
                    <form method="POST" class="modal-form">
                        <div class="form-group">
                            <label for="name">Genre Name *</label>
                            <input type="text" id="name" name="name" required 
                                   onkeyup="generateSlug(this.value)" placeholder="e.g., Action">
                        </div>
                        
                        <div class="form-group">
                            <label for="slug">Slug</label>
                            <input type="text" id="slug" name="slug" readonly class="slug-field">
                            <small>Auto-generated from name</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of this genre"></textarea>
                        </div>
                        
                        <button type="submit" name="add_genre" class="btn-primary">Add Genre</button>
                    </form>
                </div>
            </div>
            
            <!-- Edit Genre Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Edit Genre</h3>
                        <span class="close-modal">&times;</span>
                    </div>
                    
                    <form method="POST" class="modal-form" id="editForm">
                        <input type="hidden" name="genre_id" id="edit_id">
                        
                        <div class="form-group">
                            <label for="edit_name">Genre Name *</label>
                            <input type="text" id="edit_name" name="name" required 
                                   onkeyup="generateEditSlug(this.value)">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_slug">Slug</label>
                            <input type="text" id="edit_slug" name="slug" readonly class="slug-field">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_description">Description</label>
                            <textarea id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" name="edit_genre" class="btn-primary">Update Genre</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Modal functions
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        const closeBtns = document.querySelectorAll('.close-modal');
        
        function openAddModal() {
            addModal.style.display = 'block';
        }
        
        function editGenre(id) {
            // Fetch genre data
            fetch(`api/get-genre.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_name').value = data.name;
                    document.getElementById('edit_slug').value = data.slug;
                    document.getElementById('edit_description').value = data.description || '';
                    
                    editModal.style.display = 'block';
                });
        }
        
        closeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                addModal.style.display = 'none';
                editModal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target == addModal) addModal.style.display = 'none';
            if (e.target == editModal) editModal.style.display = 'none';
        });
        
        // Slug generation
        function generateSlug(name) {
            const slug = name.toLowerCase()
                .replace(/[^\w\s]/gi, '')
                .replace(/\s+/g, '-');
            document.getElementById('slug').value = slug;
        }
        
        function generateEditSlug(name) {
            const slug = name.toLowerCase()
                .replace(/[^\w\s]/gi, '')
                .replace(/\s+/g, '-');
            document.getElementById('edit_slug').value = slug;
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