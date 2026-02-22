<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Only super_admin can manage other admins
if ($_SESSION['admin_role'] !== 'super_admin') {
    header('Location: dashboard.php?error=unauthorized');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Handle Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $full_name = $_POST['full_name'];
    
    // tsek if username or email already exists
    $checkQuery = "SELECT id FROM admin_users WHERE username = ? OR email = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $message = "Username or email already exists!";
        $messageType = "error";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        $insertQuery = "INSERT INTO admin_users (username, email, password_hash, role, full_name, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($insertQuery);
        $stmt->bind_param("sssss", $username, $email, $password_hash, $role, $full_name);
        
        if ($stmt->execute()) {
            $message = "Admin added successfully!";
            $messageType = "success";
            
            // Log the action
            $logQuery = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'create', ?)";
            $logStmt = $db->prepare($logQuery);
            $details = "Added new admin: $username";
            $logStmt->bind_param("is", $_SESSION['admin_id'], $details);
            $logStmt->execute();
        } else {
            $message = "Error adding admin: " . $db->error;
            $messageType = "error";
        }
    }
}

// Handle Edit Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
    $id = $_POST['admin_id'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $full_name = $_POST['full_name'];
    
    // Check if email exists for another admin
    $checkQuery = "SELECT id FROM admin_users WHERE email = ? AND id != ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bind_param("si", $email, $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $message = "Email already exists for another admin!";
        $messageType = "error";
    } else {
        $updateQuery = "UPDATE admin_users SET email = ?, role = ?, full_name = ? WHERE id = ?";
        $stmt = $db->prepare($updateQuery);
        $stmt->bind_param("sssi", $email, $role, $full_name, $id);
        
        if ($stmt->execute()) {
            $message = "Admin updated successfully!";
            $messageType = "success";
            
            // Log the action
            $logQuery = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'update', ?)";
            $logStmt = $db->prepare($logQuery);
            $details = "Updated admin ID: $id";
            $logStmt->bind_param("is", $_SESSION['admin_id'], $details);
            $logStmt->execute();
        } else {
            $message = "Error updating admin!";
            $messageType = "error";
        }
    }
}

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $id = $_POST['admin_id'];
    $new_password = $_POST['new_password'];
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    
    $updateQuery = "UPDATE admin_users SET password_hash = ? WHERE id = ?";
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param("si", $password_hash, $id);
    
    if ($stmt->execute()) {
        $message = "Password reset successfully!";
        $messageType = "success";
        
        // Log the action
        $logQuery = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'password_reset', ?)";
        $logStmt = $db->prepare($logQuery);
        $details = "Reset password for admin ID: $id";
        $logStmt->bind_param("is", $_SESSION['admin_id'], $details);
        $logStmt->execute();
    } else {
        $message = "Error resetting password!";
        $messageType = "error";
    }
}

// Handle Delete Admin
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Prevent deleting yourself
    if ($id == $_SESSION['admin_id']) {
        $message = "You cannot delete your own account!";
        $messageType = "error";
    } else {
        // Check if this is the last super_admin
        if ($_POST['role'] === 'super_admin') {
            $checkQuery = "SELECT COUNT(*) as count FROM admin_users WHERE role = 'super_admin'";
            $checkResult = $db->query($checkQuery);
            $superCount = $checkResult->fetch_assoc()['count'];
            
            if ($superCount <= 1) {
                $message = "Cannot delete the last super administrator!";
                $messageType = "error";
            } else {
                $deleteQuery = "DELETE FROM admin_users WHERE id = ?";
                $stmt = $db->prepare($deleteQuery);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $message = "Admin deleted successfully!";
                    $messageType = "success";
                    
                    // Log the action
                    $logQuery = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'delete', ?)";
                    $logStmt = $db->prepare($logQuery);
                    $details = "Deleted admin ID: $id";
                    $logStmt->bind_param("is", $_SESSION['admin_id'], $details);
                    $logStmt->execute();
                } else {
                    $message = "Error deleting admin!";
                    $messageType = "error";
                }
            }
        } else {
            $deleteQuery = "DELETE FROM admin_users WHERE id = ?";
            $stmt = $db->prepare($deleteQuery);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "Admin deleted successfully!";
                $messageType = "success";
                
                // Log the action
                $logQuery = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'delete', ?)";
                $logStmt = $db->prepare($logQuery);
                $details = "Deleted admin ID: $id";
                $logStmt->bind_param("is", $_SESSION['admin_id'], $details);
                $logStmt->execute();
            } else {
                $message = "Error deleting admin!";
                $messageType = "error";
            }
        }
    }
}

// Get all admins
$adminsQuery = "SELECT * FROM admin_users ORDER BY 
                CASE 
                    WHEN id = ? THEN 0 
                    ELSE 1 
                END, created_at DESC";
$stmt = $db->prepare($adminsQuery);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$admins = $stmt->get_result();

// Get activity logs
$logsQuery = "SELECT al.*, au.username 
              FROM admin_logs al 
              JOIN admin_users au ON al.admin_id = au.id 
              ORDER BY al.created_at DESC 
              LIMIT 50";
$logs = $db->query($logsQuery);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrators - CineVault Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admins.css">
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
                <a href="admins.php" class="active">👤 Administrators</a>
                <a href="settings.php">⚙️ Settings</a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="admin-info">
                    <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
                    <span><?php echo htmlspecialchars($_SESSION['admin_role']); ?></span>
                </div>
                <a href="api/logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div>
                    <h2>Administrators Management</h2>
                    <p class="subtitle">Manage system administrators and their roles</p>
                </div>
                <button class="btn-primary" onclick="openModal('addModal')">
                    <span style="font-size: 18px; margin-right: 5px;">+</span> Add New Admin
                </button>
            </header>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                    <span class="close-alert" onclick="this.parentElement.remove()">&times;</span>
                </div>
            <?php endif; ?>
            
            <!-- Admin Stats -->
            <div class="stats-grid">
                <?php
                $total = $admins->num_rows;
                $super = $db->query("SELECT COUNT(*) as count FROM admin_users WHERE role = 'super_admin'")->fetch_assoc()['count'];
                $managers = $db->query("SELECT COUNT(*) as count FROM admin_users WHERE role = 'content_manager'")->fetch_assoc()['count'];
                $mods = $db->query("SELECT COUNT(*) as count FROM admin_users WHERE role = 'moderator'")->fetch_assoc()['count'];
                ?>
                <div class="stat-card">
                    <div class="stat-icon">👤</div>
                    <div class="stat-details">
                        <h3>Total Admins</h3>
                        <p class="stat-number"><?php echo $total; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-details">
                        <h3>Super Admins</h3>
                        <p class="stat-number"><?php echo $super; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-details">
                        <h3>Content Managers</h3>
                        <p class="stat-number"><?php echo $managers; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🔍</div>
                    <div class="stat-details">
                        <h3>Moderators</h3>
                        <p class="stat-number"><?php echo $mods; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Admin Cards -->
            <div class="admin-grid">
                <?php 
                $admins->data_seek(0);
                while ($admin = $admins->fetch_assoc()): 
                ?>
                <div class="admin-card <?php echo $admin['id'] == $_SESSION['admin_id'] ? 'current-user' : ''; ?>">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($admin['username'], 0, 2)); ?>
                    </div>
                    
                    <div class="admin-info">
                        <h3><?php echo htmlspecialchars($admin['full_name'] ?: $admin['username']); ?></h3>
                        <p class="admin-username">@<?php echo htmlspecialchars($admin['username']); ?></p>
                        <p class="admin-email"><?php echo htmlspecialchars($admin['email']); ?></p>
                        
                        <div class="admin-role">
                            <span class="role-badge <?php echo $admin['role']; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($admin['role'])); ?>
                            </span>
                        </div>
                        
                        <div class="admin-meta">
                            <span>📅 Joined: <?php echo date('M d, Y', strtotime($admin['created_at'])); ?></span>
                            <?php if ($admin['last_login']): ?>
                                <span>🔑 Last login: <?php echo date('M d, Y H:i', strtotime($admin['last_login'])); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="admin-actions">
                            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                <button onclick="editAdmin(<?php echo $admin['id']; ?>)" class="action-btn">
                                    ✏️ Edit
                                </button>
                                <button onclick="resetPassword(<?php echo $admin['id']; ?>)" class="action-btn">
                                    🔑 Reset Password
                                </button>
                                <a href="?delete=<?php echo $admin['id']; ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this administrator? This action cannot be undone.')">
                                    🗑️ Delete
                                </a>
                            <?php else: ?>
                                <span class="current-badge">👑 Current User</span>
                                <button onclick="editAdmin(<?php echo $admin['id']; ?>)" class="action-btn">
                                    ✏️ Edit Profile
                                </button>
                                <button onclick="resetPassword(<?php echo $admin['id']; ?>)" class="action-btn">
                                    🔑 Change Password
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Activity Log -->
            <div class="activity-log">
                <h3>📋 Recent Admin Activity</h3>
                <div class="log-timeline">
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                        <div class="log-entry">
                            <div class="log-time"><?php echo date('H:i', strtotime($log['created_at'])); ?></div>
                            <div class="log-icon">
                                <?php
                                switch ($log['action']) {
                                    case 'login': echo '🔑'; break;
                                    case 'logout': echo '🚪'; break;
                                    case 'create': echo '➕'; break;
                                    case 'update': echo '✏️'; break;
                                    case 'delete': echo '🗑️'; break;
                                    case 'password_reset': echo '🔐'; break;
                                    default: echo '📝';
                                }
                                ?>
                            </div>
                            <div class="log-details">
                                <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                <?php echo htmlspecialchars($log['action']); ?>
                                <?php if ($log['details']): ?>
                                    <span class="log-details-text">- <?php echo htmlspecialchars($log['details']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="log-entry">
                            <div class="log-details">No activity logs yet.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Add Admin Modal -->
            <div id="addModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Add New Administrator</h3>
                        <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
                    </div>
                    
                    <form method="POST" class="modal-form" onsubmit="return validateAddForm()">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required 
                                   pattern="[a-zA-Z0-9_]{3,20}" 
                                   title="3-20 characters, letters, numbers, underscore">
                            <small>3-20 characters, letters, numbers, underscore only</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" placeholder="Enter full name">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required minlength="8">
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="password-requirements">
                                <p>Password must contain:</p>
                                <ul id="passwordRequirements">
                                    <li id="lengthReq">✗ At least 8 characters</li>
                                    <li id="numberReq">✗ At least 1 number</li>
                                    <li id="letterReq">✗ At least 1 letter</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="content_manager">Content Manager</option>
                                <option value="moderator">Moderator</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="add_admin" class="btn-primary">Add Administrator</button>
                    </form>
                </div>
            </div>
            
            <!-- Edit Admin Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Edit Administrator</h3>
                        <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
                    </div>
                    
                    <form method="POST" class="modal-form" id="editForm">
                        <input type="hidden" name="admin_id" id="edit_id">
                        
                        <div class="form-group">
                            <label for="edit_username">Username</label>
                            <input type="text" id="edit_username" disabled class="disabled-field">
                            <small>Username cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_full_name">Full Name</label>
                            <input type="text" id="edit_full_name" name="full_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_email">Email Address *</label>
                            <input type="email" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_role">Role *</label>
                            <select id="edit_role" name="role" required>
                                <option value="content_manager">Content Manager</option>
                                <option value="moderator">Moderator</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="edit_admin" class="btn-primary">Update Administrator</button>
                    </form>
                </div>
            </div>
            
            <!-- Reset Password Modal -->
            <div id="resetModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Reset Password</h3>
                        <span class="close-modal" onclick="closeModal('resetModal')">&times;</span>
                    </div>
                    
                    <form method="POST" class="modal-form" onsubmit="return validatePassword()">
                        <input type="hidden" name="admin_id" id="reset_id">
                        
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="password-requirements">
                            <p>Password must contain:</p>
                            <ul>
                                <li id="resetLengthReq">✗ At least 8 characters</li>
                                <li id="resetNumberReq">✗ At least 1 number</li>
                                <li id="resetLetterReq">✗ At least 1 letter</li>
                            </ul>
                        </div>
                        
                        <button type="submit" name="reset_password" class="btn-primary">Reset Password</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
        
        // Edit admin function
        function editAdmin(id) {
            fetch(`api/get-admin.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_username').value = data.username;
                    document.getElementById('edit_full_name').value = data.full_name || '';
                    document.getElementById('edit_email').value = data.email;
                    document.getElementById('edit_role').value = data.role;
                    
                    openModal('editModal');
                })
                .catch(error => {
                    alert('Error loading admin data');
                });
        }
        
        // Reset password function
        function resetPassword(id) {
            document.getElementById('reset_id').value = id;
            openModal('resetModal');
        }
        
        // Password strength meter for add form
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            const bar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 25;
            if (password.match(/[$@#&!]+/)) strength += 25;
            
            strength = Math.min(strength, 100);
            bar.style.width = strength + '%';
            
            if (strength < 50) {
                bar.style.background = '#f44336';
            } else if (strength < 75) {
                bar.style.background = '#ff9800';
            } else {
                bar.style.background = '#4caf50';
            }
            
            // Update requirements
            document.getElementById('lengthReq').innerHTML = password.length >= 8 ? '✓ At least 8 characters' : '✗ At least 8 characters';
            document.getElementById('numberReq').innerHTML = /\d/.test(password) ? '✓ At least 1 number' : '✗ At least 1 number';
            document.getElementById('letterReq').innerHTML = /[a-zA-Z]/.test(password) ? '✓ At least 1 letter' : '✗ At least 1 letter';
            
            document.getElementById('lengthReq').style.color = password.length >= 8 ? '#4caf50' : '#f44336';
            document.getElementById('numberReq').style.color = /\d/.test(password) ? '#4caf50' : '#f44336';
            document.getElementById('letterReq').style.color = /[a-zA-Z]/.test(password) ? '#4caf50' : '#f44336';
        });
        
        // Password validation for reset form
        document.getElementById('new_password')?.addEventListener('input', checkResetPassword);
        document.getElementById('confirm_password')?.addEventListener('input', checkResetPassword);
        
        function checkResetPassword() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            // Update requirements
            document.getElementById('resetLengthReq').innerHTML = password.length >= 8 ? '✓ At least 8 characters' : '✗ At least 8 characters';
            document.getElementById('resetNumberReq').innerHTML = /\d/.test(password) ? '✓ At least 1 number' : '✗ At least 1 number';
            document.getElementById('resetLetterReq').innerHTML = /[a-zA-Z]/.test(password) ? '✓ At least 1 letter' : '✗ At least 1 letter';
            
            document.getElementById('resetLengthReq').style.color = password.length >= 8 ? '#4caf50' : '#f44336';
            document.getElementById('resetNumberReq').style.color = /\d/.test(password) ? '#4caf50' : '#f44336';
            document.getElementById('resetLetterReq').style.color = /[a-zA-Z]/.test(password) ? '#4caf50' : '#f44336';
        }
        
        // Validate add form
        function validateAddForm() {
            const password = document.getElementById('password').value;
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters!');
                return false;
            }
            
            if (!/\d/.test(password)) {
                alert('Password must contain at least 1 number!');
                return false;
            }
            
            if (!/[a-zA-Z]/.test(password)) {
                alert('Password must contain at least 1 letter!');
                return false;
            }
            
            return true;
        }
        
        // Validate password reset
        function validatePassword() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters!');
                return false;
            }
            
            if (!/\d/.test(password)) {
                alert('Password must contain at least 1 number!');
                return false;
            }
            
            if (!/[a-zA-Z]/.test(password)) {
                alert('Password must contain at least 1 letter!');
                return false;
            }
            
            return true;
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