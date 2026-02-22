<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Create settings table if not exists
$db->query("CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Insert default settings if not exists
$defaultSettings = [
    ['site_name', 'CineVault', 'text', 'Site name'],
    ['site_description', 'Watch movies and TV shows online', 'text', 'Site description'],
    ['site_keywords', 'movies, tv series, streaming, watch online', 'text', 'SEO keywords'],
    ['trial_days', '10', 'number', 'Free trial days'],
    ['enable_registration', '1', 'boolean', 'Allow new registrations'],
    ['enable_trial', '1', 'boolean', 'Enable free trial'],
    ['maintenance_mode', '0', 'boolean', 'Maintenance mode'],
    ['contact_email', 'admin@cinevault.com', 'text', 'Contact email'],
    ['support_email', 'support@cinevault.com', 'text', 'Support email'],
    ['max_watchlist_items', '50', 'number', 'Maximum items in watchlist'],
    ['items_per_page', '24', 'number', 'Items per page'],
    ['default_maturity_rating', 'TV-14', 'text', 'Default maturity rating'],
    ['enable_comments', '1', 'boolean', 'Enable comments'],
    ['enable_ratings', '1', 'boolean', 'Enable ratings'],
    ['require_email_verification', '0', 'boolean', 'Require email verification'],
    ['analytics_code', '', 'text', 'Google Analytics code'],
    ['facebook_url', '', 'text', 'Facebook page URL'],
    ['twitter_url', '', 'text', 'Twitter URL'],
    ['instagram_url', '', 'text', 'Instagram URL'],
    ['custom_css', '', 'text', 'Custom CSS'],
    ['custom_js', '', 'text', 'Custom JavaScript'],
    ['payment_currency', 'PHP', 'text', 'Payment currency'],
    ['tax_rate', '12', 'number', 'Tax rate (%)'],
    ['enable_ssl', '1', 'boolean', 'Force HTTPS']
];

foreach ($defaultSettings as $setting) {
    $check = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $check->bind_param("s", $setting[0]);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $insert = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        $insert->bind_param("ssss", $setting[0], $setting[1], $setting[2], $setting[3]);
        $insert->execute();
    }
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settingKey = substr($key, 8);
            $update = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $update->bind_param("ss", $value, $settingKey);
            $update->execute();
        }
    }
    
    // Handle file uploads
    if (!empty($_FILES['site_logo']['name'])) {
        $targetDir = "../uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $fileName = "logo_" . time() . "_" . basename($_FILES['site_logo']['name']);
        $targetFile = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $targetFile)) {
            $update = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_logo'");
            $update->bind_param("s", $fileName);
            $update->execute();
        }
    }
    
    if (!empty($_FILES['favicon']['name'])) {
        $targetDir = "../uploads/";
        $fileName = "favicon_" . time() . "_" . basename($_FILES['favicon']['name']);
        $targetFile = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES['favicon']['tmp_name'], $targetFile)) {
            $update = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'favicon'");
            $update->bind_param("s", $fileName);
            $update->execute();
        }
    }
    
    $message = "Settings saved successfully!";
    $messageType = "success";
}

// Get all settings
$settings = [];
$result = $db->query("SELECT * FROM settings ORDER BY setting_key");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CineVault Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="css/settings.css">
    
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
                <a href="genres.php">🏷️ Genres</a>
                <a href="users.php">👥 Users</a>
                <a href="admins.php">👤 Administrators</a>
                <a href="settings.php" class="active">⚙️ Settings</a>
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
                <h2>System Settings</h2>
            </header>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                    <span class="close-alert">&times;</span>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="settings-form">
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button type="button" class="tab-btn active" onclick="switchTab('general')">General</button>
                    <button type="button" class="tab-btn" onclick="switchTab('content')">Content</button>
                    <button type="button" class="tab-btn" onclick="switchTab('users')">Users</button>
                    <button type="button" class="tab-btn" onclick="switchTab('payment')">Payment</button>
                    <button type="button" class="tab-btn" onclick="switchTab('social')">Social</button>
                    <button type="button" class="tab-btn" onclick="switchTab('advanced')">Advanced</button>
                </div>
                
                <!-- General Settings -->
                <div id="general-tab" class="tab-pane active">
                    <h3>General Settings</h3>
                    
                    <div class="settings-group">
                        <div class="form-group">
                            <label>Site Name</label>
                            <input type="text" name="setting_site_name" value="<?php echo $settings['site_name']['setting_value'] ?? 'CineVault'; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Site Description</label>
                            <textarea name="setting_site_description" rows="3"><?php echo $settings['site_description']['setting_value'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>SEO Keywords</label>
                            <input type="text" name="setting_site_keywords" value="<?php echo $settings['site_keywords']['setting_value'] ?? ''; ?>">
                            <small>Comma separated keywords</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Site Logo</label>
                                <input type="file" name="site_logo" accept="image/*">
                                <?php if (!empty($settings['site_logo']['setting_value'])): ?>
                                    <div class="current-file">
                                        Current: <?php echo $settings['site_logo']['setting_value']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label>Favicon</label>
                                <input type="file" name="favicon" accept="image/x-icon,image/png">
                                <?php if (!empty($settings['favicon']['setting_value'])): ?>
                                    <div class="current-file">
                                        Current: <?php echo $settings['favicon']['setting_value']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="setting_contact_email" value="<?php echo $settings['contact_email']['setting_value'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Support Email</label>
                            <input type="email" name="setting_support_email" value="<?php echo $settings['support_email']['setting_value'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Content Settings -->
                <div id="content-tab" class="tab-pane">
                    <h3>Content Settings</h3>
                    
                    <div class="settings-group">
                        <div class="form-group">
                            <label>Items Per Page</label>
                            <input type="number" name="setting_items_per_page" value="<?php echo $settings['items_per_page']['setting_value'] ?? 24; ?>" min="1" max="100">
                        </div>
                        
                        <div class="form-group">
                            <label>Default Maturity Rating</label>
                            <select name="setting_default_maturity_rating">
                                <option value="G" <?php echo ($settings['default_maturity_rating']['setting_value'] ?? '') == 'G' ? 'selected' : ''; ?>>G</option>
                                <option value="PG" <?php echo ($settings['default_maturity_rating']['setting_value'] ?? '') == 'PG' ? 'selected' : ''; ?>>PG</option>
                                <option value="PG-13" <?php echo ($settings['default_maturity_rating']['setting_value'] ?? '') == 'PG-13' ? 'selected' : ''; ?>>PG-13</option>
                                <option value="R" <?php echo ($settings['default_maturity_rating']['setting_value'] ?? '') == 'R' ? 'selected' : ''; ?>>R</option>
                                <option value="TV-14" <?php echo ($settings['default_maturity_rating']['setting_value'] ?? '') == 'TV-14' ? 'selected' : ''; ?>>TV-14</option>
                                <option value="TV-MA" <?php echo ($settings['default_maturity_rating']['setting_value'] ?? '') == 'TV-MA' ? 'selected' : ''; ?>>TV-MA</option>
                            </select>
                        </div>
                        
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="setting_enable_comments" value="1" <?php echo ($settings['enable_comments']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Enable Comments
                            </label>
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="setting_enable_ratings" value="1" <?php echo ($settings['enable_ratings']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Enable Ratings
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Users Settings -->
                <div id="users-tab" class="tab-pane">
                    <h3>User Settings</h3>
                    
                    <div class="settings-group">
                        <div class="form-group">
                            <label>Free Trial Days</label>
                            <input type="number" name="setting_trial_days" value="<?php echo $settings['trial_days']['setting_value'] ?? 10; ?>" min="0" max="90">
                        </div>
                        
                        <div class="form-group">
                            <label>Max Watchlist Items</label>
                            <input type="number" name="setting_max_watchlist_items" value="<?php echo $settings['max_watchlist_items']['setting_value'] ?? 50; ?>" min="1" max="500">
                        </div>
                        
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="setting_enable_registration" value="1" <?php echo ($settings['enable_registration']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Enable New Registrations
                            </label>
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="setting_enable_trial" value="1" <?php echo ($settings['enable_trial']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Enable Free Trial
                            </label>
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="setting_require_email_verification" value="1" <?php echo ($settings['require_email_verification']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                Require Email Verification
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Settings -->
                <div id="payment-tab" class="tab-pane">
                    <h3>Payment Settings</h3>
                    
                    <div class="settings-group">
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="setting_payment_currency">
                                <option value="PHP" <?php echo ($settings['payment_currency']['setting_value'] ?? 'PHP') == 'PHP' ? 'selected' : ''; ?>>PHP - Philippine Peso</option>
                                <option value="USD" <?php echo ($settings['payment_currency']['setting_value'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo ($settings['payment_currency']['setting_value'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tax Rate (%)</label>
                            <input type="number" name="setting_tax_rate" value="<?php echo $settings['tax_rate']['setting_value'] ?? 12; ?>" min="0" max="100" step="0.1">
                        </div>
                        
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="setting_enable_ssl" value="1" <?php echo ($settings['enable_ssl']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Force HTTPS (SSL)
                            </label>
                        </div>
                        
                        <h4>Payment Gateways</h4>
                        <div class="payment-gateways">
                            <div class="gateway-item">
                                <input type="checkbox" id="paypal" checked disabled>
                                <label for="paypal">PayPal (Coming Soon)</label>
                            </div>
                            <div class="gateway-item">
                                <input type="checkbox" id="stripe" checked disabled>
                                <label for="stripe">Stripe (Coming Soon)</label>
                            </div>
                            <div class="gateway-item">
                                <input type="checkbox" id="gcash" checked disabled>
                                <label for="gcash">GCash (Coming Soon)</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Social Settings -->
                <div id="social-tab" class="tab-pane">
                    <h3>Social Media Links</h3>
                    
                    <div class="settings-group">
                        <div class="form-group">
                            <label>Facebook URL</label>
                            <input type="url" name="setting_facebook_url" value="<?php echo $settings['facebook_url']['setting_value'] ?? ''; ?>" placeholder="https://facebook.com/...">
                        </div>
                        
                        <div class="form-group">
                            <label>Twitter URL</label>
                            <input type="url" name="setting_twitter_url" value="<?php echo $settings['twitter_url']['setting_value'] ?? ''; ?>" placeholder="https://twitter.com/...">
                        </div>
                        
                        <div class="form-group">
                            <label>Instagram URL</label>
                            <input type="url" name="setting_instagram_url" value="<?php echo $settings['instagram_url']['setting_value'] ?? ''; ?>" placeholder="https://instagram.com/...">
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Settings -->
                <div id="advanced-tab" class="tab-pane">
                    <h3>Advanced Settings</h3>
                    
                    <div class="settings-group">
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="setting_maintenance_mode" value="1" <?php echo ($settings['maintenance_mode']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                Maintenance Mode
                            </label>
                            <p class="warning-text">When enabled, only admins can access the site</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Google Analytics Code</label>
                            <textarea name="setting_analytics_code" rows="3" placeholder="UA-XXXXX-Y or G-XXXXX"><?php echo $settings['analytics_code']['setting_value'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Custom CSS</label>
                            <textarea name="setting_custom_css" rows="5" class="code-editor"><?php echo $settings['custom_css']['setting_value'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Custom JavaScript</label>
                            <textarea name="setting_custom_js" rows="5" class="code-editor"><?php echo $settings['custom_js']['setting_value'] ?? ''; ?></textarea>
                        </div>
                    </div>
                    
                    <h3>System Information</h3>
                    <div class="system-info">
                        <div class="info-row">
                            <span class="info-label">PHP Version:</span>
                            <span class="info-value"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">MySQL Version:</span>
                            <span class="info-value"><?php echo $db->query("SELECT VERSION()")->fetch_row()[0]; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Server Software:</span>
                            <span class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Upload Max Size:</span>
                            <span class="info-value"><?php echo ini_get('upload_max_filesize'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Memory Limit:</span>
                            <span class="info-value"><?php echo ini_get('memory_limit'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Max Execution Time:</span>
                            <span class="info-value"><?php echo ini_get('max_execution_time'); ?> seconds</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save All Settings</button>
                    <button type="reset" class="btn-secondary">Reset to Default</button>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-pane').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        // Close alerts
        document.querySelectorAll('.close-alert').forEach(btn => {
            btn.addEventListener('click', function() {
                this.parentElement.remove();
            });
        });
        
        // Confirm reset
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Reset all settings to default? This cannot be undone.')) {
                // Reset logic here
            }
        });
    </script>
    
   
</body>
</html>