<?php
require_once '../includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome Back - CineVault</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-header">
            <h1 class="logo">CineVault</h1>
            <p class="tagline">Welcome back to your cinema</p>
        </div>
        
        <div class="auth-card">
            <h2>Sign In</h2>
            
            <form id="loginForm" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <div class="form-options">
                    <div class="form-check">
                        <input type="checkbox" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn-primary btn-block">Sign In</button>
            </form>
            
            <div class="auth-footer">
                <p>New to CineVault? <a href="register.php">Start Free Trial</a></p>
            </div>
        </div>
    </div>
    
    <div id="messageContainer"></div>
    <script src="../assets/js/main.js"></script>
</body>
</html>