<?php
session_start();

require_once '../includes/database.php';
$db = getDB();

// Configuration - Change these in production
define('SETUP_SECRET_KEY', 'CINEVAULT_ADMIN_SETUP_2026'); // Change this!
define('MAX_SETUP_ATTEMPTS', 3);
define('SETUP_TIMEOUT', 30); // minutes

// Check if setup is allowed
$setupAllowed = true;
$setupMessage = '';

// Check if admin already exists
$checkAdmin = $db->query("SELECT COUNT(*) as count FROM admin_users");
$adminCount = $checkAdmin->fetch_assoc()['count'];

if ($adminCount > 0) {
    $setupAllowed = false;
    $setupMessage = 'Admin accounts already exist.';
}

// Check setup attempts (prevent brute force)
$attemptsFile = __DIR__ . '/setup_attempts.txt';
$currentAttempts = 0;
if (file_exists($attemptsFile)) {
    $attemptData = json_decode(file_get_contents($attemptsFile), true);
    if ($attemptData && isset($attemptData['attempts'])) {
        $currentAttempts = $attemptData['attempts'];
        $lastAttempt = $attemptData['last_attempt'] ?? 0;
        
        // Reset attempts after timeout
        if (time() - $lastAttempt > SETUP_TIMEOUT * 60) {
            $currentAttempts = 0;
        }
    }
}

if ($currentAttempts >= MAX_SETUP_ATTEMPTS) {
    $setupAllowed = false;
    $setupMessage = 'Too many failed setup attempts. Please try again later.';
}

// Process form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setupAllowed) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $setup_key = $_POST['setup_key'] ?? '';
    $setup_token = $_POST['setup_token'] ?? '';
    
    // Verify session token (CSRF protection)
    if (!isset($_SESSION['setup_token']) || $setup_token !== $_SESSION['setup_token']) {
        $message = '<div class="error">❌ Invalid session token</div>';
    }
    // Verify setup key
    elseif ($setup_key !== SETUP_SECRET_KEY) {
        $currentAttempts++;
        file_put_contents($attemptsFile, json_encode([
            'attempts' => $currentAttempts,
            'last_attempt' => time()
        ]));
        $message = '<div class="error">❌ Invalid setup key</div>';
    }
    elseif ($password !== $confirm_password) {
        $message = '<div class="error">❌ Passwords do not match</div>';
    }
    elseif (strlen($password) < 8) {
        $message = '<div class="error">❌ Password must be at least 8 characters</div>';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="error">❌ Invalid email format</div>';
    }
    else {
        // Create admin account
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $full_name = $_POST['full_name'] ?? $username;
        
        // Generate backup codes
        $backup_codes = [];
        for ($i = 0; $i < 5; $i++) {
            $backup_codes[] = bin2hex(random_bytes(5));
        }
        $backup_codes_json = json_encode($backup_codes);
        
        $insertQuery = "INSERT INTO admin_users (username, email, password_hash, role, full_name, backup_codes, created_at) 
                        VALUES (?, ?, ?, 'super_admin', ?, ?, NOW())";
        $stmt = $db->prepare($insertQuery);
        $stmt->bind_param("sssss", $username, $email, $password_hash, $full_name, $backup_codes_json);
        
        if ($stmt->execute()) {
            // Success message with backup codes
            $message = '<div class="success">';
            $message .= '<h3>✅ Admin Account Created Successfully!</h3>';
            $message .= '<p><strong>Username:</strong> ' . htmlspecialchars($username) . '</p>';
            $message .= '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>';
            $message .= '<hr>';
            $message .= '<h4>🔐 Backup Recovery Codes</h4>';
            $message .= '<p>Save these codes in a secure place. They can be used to recover your account.</p>';
            $message .= '<div style="background: #000; padding: 15px; border-radius: 5px; margin: 15px 0;">';
            foreach ($backup_codes as $index => $code) {
                $message .= '<code style="display: block; color: #4caf50; font-family: monospace; margin: 5px 0;">' . ($index + 1) . '. ' . $code . '</code>';
            }
            $message .= '</div>';
            $message .= '<hr>';
            $message .= '<h4>🔒 Security Actions:</h4>';
            
            // Delete this setup file
            $setupFile = __FILE__;
            if (unlink($setupFile)) {
                $message .= '<p>✅ Setup page deleted automatically</p>';
            }
            
            // Delete attempts file
            if (file_exists($attemptsFile)) {
                unlink($attemptsFile);
                $message .= '<p>✅ Attempt logs cleared</p>';
            }
            
            // Clear session
            session_destroy();
            
            $message .= '<p style="margin-top: 20px;"><a href="login.php" class="btn">🔑 Go to Admin Login</a></p>';
            $message .= '</div>';
            
            // Stop further execution
            echo $message;
            exit();
        } else {
            $message = '<div class="error">❌ Failed to create admin: ' . $db->error . '</div>';
        }
    }
}

// Generate CSRF token
$_SESSION['setup_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Admin Setup - CineVault</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #141414, #000000);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .setup-container {
            max-width: 550px;
            width: 100%;
        }
        
        .setup-box {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 40px;
            border: 1px solid #333;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        
        h1 {
            color: #e50914;
            font-size: 36px;
            margin-bottom: 5px;
            text-align: center;
        }
        
        .subtitle {
            color: #b3b3b3;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .security-badge {
            background: linear-gradient(135deg, #e50914, #b2070f);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: bold;
            position: relative;
            overflow: hidden;
        }
        
        .security-badge::before {
            content: "🔒";
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
        }
        
        .security-badge::after {
            content: "🔒";
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
        }
        
        .attempts-counter {
            text-align: right;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .attempts-counter .used {
            color: #ff9800;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #b3b3b3;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 14px 16px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            color: white;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #e50914;
            background: #2a2a2a;
        }
        
        .password-strength {
            height: 4px;
            background: #333;
            margin-top: 8px;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background 0.3s;
        }
        
        button {
            width: 100%;
            padding: 16px;
            background: #e50914;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px 0 10px;
        }
        
        button:hover:not(:disabled) {
            background: #b2070f;
            transform: translateY(-2px);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .success {
            background: rgba(76, 175, 80, 0.1);
            border-left: 4px solid #4caf50;
            color: #4caf50;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .error {
            background: rgba(244, 67, 54, 0.1);
            border-left: 4px solid #f44336;
            color: #f44336;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            background: #e50914;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .setup-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .setup-info h3 {
            color: #e50914;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .setup-info ul {
            color: #b3b3b3;
            font-size: 13px;
            margin-left: 20px;
        }
        
        .setup-info li {
            margin: 8px 0;
        }
        
        .setup-info .danger {
            color: #f44336;
        }
        
        hr {
            border: none;
            border-top: 1px solid #333;
            margin: 20px 0;
        }
        
        code {
            background: #1a1a1a;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-box">
            <h1>CineVault</h1>
            <p class="subtitle">Secure One-Time Admin Setup</p>
            
            <div class="security-badge">
                SELF-DESTRUCTING SETUP PAGE
            </div>
            
            <?php if (!$setupAllowed): ?>
                <div class="error">
                    <h3>⛔ Setup Not Allowed</h3>
                    <p><?php echo $setupMessage; ?></p>
                    <hr>
                    <p><a href="login.php" class="btn">Go to Login</a></p>
                </div>
            <?php else: ?>
                
                <?php echo $message; ?>
                
                <div class="attempts-counter">
                    Attempts: <span class="used"><?php echo $currentAttempts; ?></span>/<?php echo MAX_SETUP_ATTEMPTS; ?>
                </div>
                
                <form method="POST" id="setupForm">
                    <input type="hidden" name="setup_token" value="<?php echo $_SESSION['setup_token']; ?>">
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required placeholder="admin" pattern="[a-zA-Z0-9_]{3,20}" title="3-20 characters, letters, numbers, underscore">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" placeholder="Administrator">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required placeholder="admin@cinevault.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required minlength="8" placeholder="••••••••">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="••••••••">
                    </div>
                    
                    <div class="form-group">
                        <label for="setup_key">Setup Security Key *</label>
                        <input type="text" id="setup_key" name="setup_key" required class="setup-key-input" placeholder="Enter setup key">
                        <small style="color: #666; display: block; margin-top: 5px;">Default key: <code>CINEVAULT_ADMIN_SETUP_2026</code></small>
                    </div>
                    
                    <button type="submit" id="submitBtn" <?php echo $currentAttempts >= MAX_SETUP_ATTEMPTS ? 'disabled' : ''; ?>>
                        🔐 Create Admin Account
                    </button>
                </form>
                
                <div class="setup-info">
                    <h3>⚠️ Important Information</h3>
                    <ul>
                        <li><strong>ONE-TIME SETUP:</strong> This page will self-destruct after successful creation</li>
                        <li><strong>BACKUP CODES:</strong> 5 recovery codes will be generated for account recovery</li>
                        <li><strong>MAX ATTEMPTS:</strong> Only <?php echo MAX_SETUP_ATTEMPTS; ?> attempts allowed</li>
                        <li><strong class="danger">SECURITY:</strong> Save your credentials immediately</li>
                    </ul>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Password strength meter
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 25;
            if (password.match(/[$@#&!]+/)) strength += 25;
            
            strength = Math.min(strength, 100);
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.style.background = '#f44336';
            } else if (strength < 75) {
                strengthBar.style.background = '#ff9800';
            } else {
                strengthBar.style.background = '#4caf50';
            }
        });
        
        // Form validation
        document.getElementById('setupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('❌ Passwords do not match!');
                return;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('❌ Password must be at least 8 characters!');
                return;
            }
            
            if (!confirm('⚠️ This is a ONE-TIME setup. After creation, this page will be deleted.\n\nAre you ABSOLUTELY sure?')) {
                e.preventDefault();
                return;
            }
        });
        

        document.getElementById('submitBtn').addEventListener('click', function() {
            setTimeout(() => {
                this.disabled = true;
                this.textContent = 'Processing...';
            }, 100);
        });
    </script>
</body>
</html>