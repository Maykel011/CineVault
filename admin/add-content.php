<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Get genres for dropdown
$genres = $db->query("SELECT * FROM genres ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Content - CineVault Admin</title>
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
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="content.php">🎬 Content</a>
                <a href="movies.php">🎥 Movies</a>
                <a href="series.php">📺 Series</a>
                <a href="genres.php">🏷️ Genres</a>
                <a href="users.php">👥 Users</a>
                <a href="admins.php">👤 Administrators</a>
                <a href="settings.php">⚙️ Settings</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <header class="content-header">
                <h2>Add New Content</h2>
                <a href="content.php" class="btn-secondary">← Back to Content</a>
            </header>
            
            <div class="form-container">
                <form id="addContentForm" enctype="multipart/form-data">
                    <div class="form-tabs">
                        <button type="button" class="tab-btn active" onclick="switchTab('basic')">Basic Info</button>
                        <button type="button" class="tab-btn" onclick="switchTab('media')">Media</button>
                        <button type="button" class="tab-btn" onclick="switchTab('cast')">Cast & Crew</button>
                        <button type="button" class="tab-btn" onclick="switchTab('seasons')">Seasons (for Series)</button>
                    </div>
                    
                    <!-- Basic Info Tab -->
                    <div id="basic-tab" class="tab-content active">
                        <div class="form-group">
                            <label for="title">Title *</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" rows="5" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="content_type">Content Type *</label>
                                <select id="content_type" name="content_type" required onchange="toggleSeriesFields()">
                                    <option value="">Select Type</option>
                                    <option value="movie">Movie</option>
                                    <option value="series">Series</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="genre_id">Genre *</label>
                                <select id="genre_id" name="genre_id" required>
                                    <option value="">Select Genre</option>
                                    <?php while ($genre = $genres->fetch_assoc()): ?>
                                    <option value="<?php echo $genre['id']; ?>"><?php echo $genre['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="release_year">Release Year *</label>
                                <input type="number" id="release_year" name="release_year" min="1900" max="<?php echo date('Y') + 5; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration_minutes">Duration (minutes) *</label>
                                <input type="number" id="duration_minutes" name="duration_minutes" min="1" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="maturity_rating">Maturity Rating</label>
                                <select id="maturity_rating" name="maturity_rating">
                                    <option value="G">G</option>
                                    <option value="PG">PG</option>
                                    <option value="PG-13">PG-13</option>
                                    <option value="R">R</option>
                                    <option value="TV-14">TV-14</option>
                                    <option value="TV-MA">TV-MA</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="rating">Initial Rating (0-10)</label>
                                <input type="number" id="rating" name="rating" min="0" max="10" step="0.1">
                            </div>
                        </div>
                        
                        <div class="form-checkbox">
                            <label>
                                <input type="checkbox" name="featured"> Featured Content
                            </label>
                            <label>
                                <input type="checkbox" name="trending"> Trending
                            </label>
                        </div>
                    </div>
                    
                    <!-- Media Tab -->
                    <div id="media-tab" class="tab-content">
                        <div class="form-group">
                            <label for="poster_image">Poster Image</label>
                            <input type="file" id="poster_image" name="poster_image" accept="image/*">
                            <small>Recommended size: 500x750px</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="cover_image">Cover Image</label>
                            <input type="file" id="cover_image" name="cover_image" accept="image/*">
                            <small>Recommended size: 1920x1080px</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="trailer_url">Trailer URL (YouTube)</label>
                            <input type="url" id="trailer_url" name="trailer_url" placeholder="https://youtube.com/watch?v=...">
                        </div>
                        
                        <div class="form-group video-sources">
                            <label>Video Sources</label>
                            <div class="video-source-row">
                                <select name="quality[]" class="quality-select">
                                    <option value="720p">720p</option>
                                    <option value="1080p">1080p</option>
                                    <option value="4K">4K</option>
                                </select>
                                <input type="url" name="video_url[]" placeholder="Video URL" class="video-url">
                                <button type="button" class="btn-small" onclick="addVideoSource()">+ Add</button>
                            </div>
                            <div id="video-sources-container"></div>
                        </div>
                    </div>
                    
                    <!-- Cast & Crew Tab -->
                    <div id="cast-tab" class="tab-content">
                        <div class="cast-section">
                            <h3>Cast</h3>
                            <div id="cast-container">
                                <div class="cast-row">
                                    <input type="text" name="actor_name[]" placeholder="Actor Name">
                                    <input type="text" name="character_name[]" placeholder="Character Name">
                                    <button type="button" class="btn-small" onclick="addCast()">+</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="crew-section">
                            <h3>Director</h3>
                            <input type="text" name="director" placeholder="Director Name">
                            
                            <h3>Writer</h3>
                            <input type="text" name="writer" placeholder="Writer Name">
                        </div>
                    </div>
                    
                    <!-- Seasons Tab (this is for Series) -->
                    <div id="seasons-tab" class="tab-content">
                        <div id="seasons-container">
                            <div class="season-item">
                                <h4>Season 1</h4>
                                <div class="season-fields">
                                    <input type="text" name="season_title[]" placeholder="Season Title">
                                    <textarea name="season_description[]" placeholder="Season Description"></textarea>
                                    
                                    <div class="episodes">
                                        <h5>Episodes</h5>
                                        <div class="episode-row">
                                            <input type="text" name="episode_title[][]" placeholder="Episode Title">
                                            <input type="number" name="episode_duration[][]" placeholder="Duration (min)">
                                            <button type="button" onclick="addEpisode(this)">+ Add Episode</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-secondary" onclick="addSeason()">+ Add Season</button>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save Content</button>
                        <button type="reset" class="btn-secondary">Reset</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        function toggleSeriesFields() {
            const type = document.getElementById('content_type').value;
            const seasonsTab = document.getElementById('seasons-tab');
            const durationField = document.getElementById('duration_minutes');
            
            if (type === 'series') {
                seasonsTab.style.display = 'block';
                durationField.disabled = true;
            } else {
                seasonsTab.style.display = 'none';
                durationField.disabled = false;
            }
        }
        
        function addVideoSource() {
            const container = document.getElementById('video-sources-container');
            const row = document.createElement('div');
            row.className = 'video-source-row';
            row.innerHTML = `
                <select name="quality[]" class="quality-select">
                    <option value="720p">720p</option>
                    <option value="1080p">1080p</option>
                    <option value="4K">4K</option>
                </select>
                <input type="url" name="video_url[]" placeholder="Video URL" class="video-url">
                <button type="button" class="btn-small" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(row);
        }
        
        function addCast() {
            const container = document.getElementById('cast-container');
            const row = document.createElement('div');
            row.className = 'cast-row';
            row.innerHTML = `
                <input type="text" name="actor_name[]" placeholder="Actor Name">
                <input type="text" name="character_name[]" placeholder="Character Name">
                <button type="button" class="btn-small" onclick="this.parentElement.remove()">-</button>
            `;
            container.appendChild(row);
        }
        
        let seasonCount = 1;
        
        function addSeason() {
            seasonCount++;
            const container = document.getElementById('seasons-container');
            const season = document.createElement('div');
            season.className = 'season-item';
            season.innerHTML = `
                <h4>Season ${seasonCount}</h4>
                <div class="season-fields">
                    <input type="text" name="season_title[]" placeholder="Season Title">
                    <textarea name="season_description[]" placeholder="Season Description"></textarea>
                    
                    <div class="episodes">
                        <h5>Episodes</h5>
                        <div class="episode-row">
                            <input type="text" name="episode_title[${seasonCount-1}][]" placeholder="Episode Title">
                            <input type="number" name="episode_duration[${seasonCount-1}][]" placeholder="Duration (min)">
                            <button type="button" onclick="addEpisode(this, ${seasonCount-1})">+ Add Episode</button>
                        </div>
                    </div>
                    <button type="button" class="btn-small" onclick="this.parentElement.parentElement.remove()">Remove Season</button>
                </div>
            `;
            container.appendChild(season);
        }
        
        function addEpisode(button, seasonIndex) {
            const episodeRow = button.parentElement.cloneNode(true);
            episodeRow.querySelector('input').value = '';
            episodeRow.querySelector('input[type="number"]').value = '';
            episodeRow.innerHTML += '<button type="button" onclick="this.parentElement.remove()">-</button>';
            button.parentElement.parentElement.appendChild(episodeRow);
        }
        
        // Form submission
        document.getElementById('addContentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('api/save-content.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Content saved successfully!');
                    window.location.href = 'content.php';
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error saving content. Please try again.');
                console.error(error);
            }
        });
    </script>
</body>
</html>