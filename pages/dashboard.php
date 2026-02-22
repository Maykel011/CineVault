<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit();
}

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = getDB();

// Get user's subscription details
$subQuery = "SELECT us.*, sp.plan_name, sp.resolution, sp.screens 
             FROM user_subscriptions us 
             JOIN subscription_plans sp ON us.plan_id = sp.id 
             WHERE us.user_id = ? AND us.payment_status = 'paid' 
             ORDER BY us.created_at DESC LIMIT 1";
$stmt = $db->prepare($subQuery);
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();

// Calculate days left in trial
$today = new DateTime();
$trialEnd = new DateTime($user['trial_end']);
$trialDaysLeft = $today->diff($trialEnd)->days;
$trialPercentage = ($trialDaysLeft / TRIAL_DAYS) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineVault - Your Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="main-nav">
        <div class="nav-container">
            <div class="nav-left">
                <h1 class="logo">CineVault</h1>
                <ul class="nav-links">
                    <li><a href="#" class="active">Home</a></li>
                    <li><a href="#">Movies</a></li>
                    <li><a href="#">TV Shows</a></li>
                    <li><a href="#">My List</a></li>
                </ul>
            </div>
            
            <div class="nav-right">
                <div class="user-menu">
                    <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                    <div class="user-dropdown">
                        <a href="#">Account Settings</a>
                        <a href="#">Help</a>
                        <a href="../api/logout.php">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="dashboard">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                <p class="hero-subtitle">Your next favorite movie is waiting</p>
                
                <?php if (!$subscription): ?>
                <div class="trial-alert">
                    <div class="trial-info">
                        <span class="trial-badge">FREE TRIAL</span>
                        <p>You have <strong><?php echo $trialDaysLeft; ?> days</strong> left in your trial</p>
                    </div>
                    <div class="trial-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo 100 - $trialPercentage; ?>%"></div>
                        </div>
                    </div>
                    <a href="plans.php" class="btn-primary btn-small">Choose a Plan</a>
                </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Subscription Status Card -->
        <?php if ($subscription): ?>
        <section class="subscription-card">
            <div class="card-header">
                <h2>Your Membership</h2>
                <span class="status-badge active">Active</span>
            </div>
            <div class="card-content">
                <div class="plan-details">
                    <div class="plan-name"><?php echo $subscription['plan_name']; ?> Plan</div>
                    <div class="plan-features">
                        <span class="feature"><?php echo $subscription['resolution']; ?></span>
                        <span class="feature"><?php echo $subscription['screens']; ?> screens</span>
                    </div>
                </div>
                <div class="billing-details">
                    <p>Next billing date: <?php echo date('F j, Y', strtotime($subscription['end_date'])); ?></p>
                    <a href="#" class="link">Manage Plan</a>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Content Rows -->
        <section class="content-row">
            <h2>Trending Now</h2>
            <div class="movie-grid">
                <!-- Movie cards would be dynamically loaded here -->
                <div class="movie-card">
                    <div class="movie-placeholder"></div>
                    <div class="movie-info">
                        <h4>Movie Title</h4>
                        <p>2024</p>
                    </div>
                </div>
                <!-- Repeat for more movies -->
            </div>
        </section>
        
        <section class="content-row">
            <h2>Continue Watching</h2>
            <div class="movie-grid">
                <div class="movie-card continue">
                    <div class="movie-placeholder"></div>
                    <div class="progress-indicator" style="width: 65%"></div>
                    <div class="movie-info">
                        <h4>Movie Title</h4>
                        <p>65% watched</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>