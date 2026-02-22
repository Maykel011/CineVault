<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/database.php';
$db = getDB();

// Handle user actions
if (isset($_GET['action'])) {
    $userId = (int)$_GET['user_id'];
    
    switch ($_GET['action']) {
        case 'suspend':
            $update = "UPDATE users SET account_status = 'suspended' WHERE id = ?";
            break;
        case 'activate':
            $update = "UPDATE users SET account_status = 'active' WHERE id = ?";
            break;
        case 'delete':
            // Check if user has subscriptions
            $checkSub = "SELECT COUNT(*) as count FROM user_subscriptions WHERE user_id = ?";
            $stmt = $db->prepare($checkSub);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $subCount = $stmt->get_result()->fetch_assoc()['count'];
            
            if ($subCount > 0) {
                $message = "Cannot delete user - they have active subscriptions!";
                $messageType = "error";
                break;
            } else {
                $update = "DELETE FROM users WHERE id = ?";
            }
            break;
        default:
            $update = null;
    }
    
    if (isset($update)) {
        $stmt = $db->prepare($update);
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $message = "User updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating user!";
            $messageType = "error";
        }
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$planFilter = $_GET['plan'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT u.*, 
          COUNT(DISTINCT wh.id) as watch_count,
          COUNT(DISTINCT wl.id) as watchlist_count,
          COUNT(DISTINCT us.id) as subscription_count,
          MAX(us.end_date) as subscription_end
          FROM users u
          LEFT JOIN watch_history wh ON u.id = wh.user_id
          LEFT JOIN watchlist wl ON u.id = wl.user_id
          LEFT JOIN user_subscriptions us ON u.id = us.user_id
          WHERE 1=1";

$params = [];
$types = "";

if ($statusFilter) {
    $query .= " AND u.account_status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($planFilter) {
    $query .= " AND u.current_plan = ?";
    $params[] = $planFilter;
    $types .= "s";
}

if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Get statistics
$statsQuery = "SELECT 
                COUNT(*) as total_users,
                SUM(account_status = 'active') as active_users,
                SUM(account_status = 'trial') as trial_users,
                SUM(account_status = 'suspended') as suspended_users,
                SUM(current_plan = 'basic') as basic_plan,
                SUM(current_plan = 'standard') as standard_plan,
                SUM(current_plan = 'premium') as premium_plan
                FROM users";
$stats = $db->query($statsQuery)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - CineVault Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="css/users.css">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
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
                <a href="movies.php">🎥 Movies</a>
                <a href="series.php">📺 Series</a>
                <a href="genres.php">🏷️ Genres</a>
                <a href="users.php" class="active">👥 Users</a>
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
                    <h2>Users Management</h2>
                    <p class="subtitle">Manage your subscribers and their accounts</p>
                </div>
                <div class="header-actions">
                    <a href="export.php?type=users" class="btn-secondary">📥 Export Users</a>
                    <a href="send-newsletter.php" class="btn-primary">📧 Send Newsletter</a>
                </div>
            </header>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                    <span class="close-alert">&times;</span>
                </div>
            <?php endif; ?>
            
            <!-- User Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-details">
                        <h3>Total Users</h3>
                        <p class="stat-number"><?php echo $stats['total_users']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-details">
                        <h3>Active</h3>
                        <p class="stat-number"><?php echo $stats['active_users']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🆓</div>
                    <div class="stat-details">
                        <h3>Trial</h3>
                        <p class="stat-number"><?php echo $stats['trial_users']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⛔</div>
                    <div class="stat-details">
                        <h3>Suspended</h3>
                        <p class="stat-number"><?php echo $stats['suspended_users']; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Plan Distribution -->
            <div class="plan-distribution">
                <h3>Plan Distribution</h3>
                <div class="plan-bars">
                    <div class="plan-bar-item">
                        <span class="plan-label">Basic (₱150)</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($stats['basic_plan'] / max(1, $stats['total_users'])) * 100; ?>%">
                                <?php echo $stats['basic_plan']; ?> users
                            </div>
                        </div>
                    </div>
                    <div class="plan-bar-item">
                        <span class="plan-label">Standard (₱250)</span>
                        <div class="progress-bar">
                            <div class="progress-fill standard" style="width: <?php echo ($stats['standard_plan'] / max(1, $stats['total_users'])) * 100; ?>%">
                                <?php echo $stats['standard_plan']; ?> users
                            </div>
                        </div>
                    </div>
                    <div class="plan-bar-item">
                        <span class="plan-label">Premium (₱400)</span>
                        <div class="progress-bar">
                            <div class="progress-fill premium" style="width: <?php echo ($stats['premium_plan'] / max(1, $stats['total_users'])) * 100; ?>%">
                                <?php echo $stats['premium_plan']; ?> users
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-options">
                    <select id="statusFilter" onchange="applyFilters()">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="trial" <?php echo $statusFilter == 'trial' ? 'selected' : ''; ?>>Trial</option>
                        <option value="suspended" <?php echo $statusFilter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="expired" <?php echo $statusFilter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                    
                    <select id="planFilter" onchange="applyFilters()">
                        <option value="">All Plans</option>
                        <option value="basic" <?php echo $planFilter == 'basic' ? 'selected' : ''; ?>>Basic</option>
                        <option value="standard" <?php echo $planFilter == 'standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="premium" <?php echo $planFilter == 'premium' ? 'selected' : ''; ?>>Premium</option>
                    </select>
                    
                    <select id="dateRange">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <button onclick="resetFilters()" class="btn-secondary">Reset Filters</button>
            </div>
            
            <!-- Users Table -->
            <div class="table-responsive">
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Plan</th>
                            <th>Joined</th>
                            <th>Subscription End</th>
                            <th>Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $user['id']; ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $user['email']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['account_status']; ?>">
                                    <?php echo ucfirst($user['account_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['current_plan']): ?>
                                    <span class="plan-badge <?php echo $user['current_plan']; ?>">
                                        <?php echo ucfirst($user['current_plan']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="plan-badge none">No Plan</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['subscription_end']): ?>
                                    <?php 
                                    $end = strtotime($user['subscription_end']);
                                    $now = time();
                                    $daysLeft = ceil(($end - $now) / 86400);
                                    ?>
                                    <?php if ($daysLeft > 0): ?>
                                        <span class="days-left positive"><?php echo $daysLeft; ?> days left</span>
                                    <?php else: ?>
                                        <span class="days-left expired">Expired</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="days-left">No subscription</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="activity-stats">
                                    <span title="Watch History">👁️ <?php echo $user['watch_count']; ?></span>
                                    <span title="Watchlist">📋 <?php echo $user['watchlist_count']; ?></span>
                                </div>
                            </td>
                            <td class="actions">
                                <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn-icon" title="View Details">👁️</a>
                                <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn-icon" title="Edit">✏️</a>
                                
                                <?php if ($user['account_status'] == 'active'): ?>
                                    <a href="?action=suspend&user_id=<?php echo $user['id']; ?>" class="btn-icon" title="Suspend">⛔</a>
                                <?php elseif ($user['account_status'] == 'suspended'): ?>
                                    <a href="?action=activate&user_id=<?php echo $user['id']; ?>" class="btn-icon" title="Activate">✅</a>
                                <?php endif; ?>
                                
                                <a href="user-history.php?id=<?php echo $user['id']; ?>" class="btn-icon" title="Watch History">📊</a>
                                <a href="?action=delete&user_id=<?php echo $user['id']; ?>" class="btn-icon delete" onclick="return confirm('Delete this user?')" title="Delete">🗑️</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });
        });
        
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const plan = document.getElementById('planFilter').value;
            const search = document.getElementById('searchInput').value;
            
            window.location.href = `users.php?status=${status}&plan=${plan}&search=${encodeURIComponent(search)}`;
        }
        
        function resetFilters() {
            window.location.href = 'users.php';
        }
        
        // Search on enter
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    </script>
    
   
</body>
</html>