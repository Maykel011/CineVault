<?php
require_once 'database.php';

class Auth {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    public function register($userData) {
        $username = $this->db->escape($userData['username']);
        $email = $this->db->escape($userData['email']);
        $password = $userData['password'];
        
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        // Check if user exists
        $checkQuery = "SELECT id FROM users WHERE email = ? OR username = ?";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Email or username already taken'];
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        // Set trial dates
        $trialStart = date('Y-m-d');
        $trialEnd = date('Y-m-d', strtotime('+' . TRIAL_DAYS . ' days'));
        
        // Insert user
        $insertQuery = "INSERT INTO users (username, email, password_hash, trial_start, trial_end) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($insertQuery);
        $stmt->bind_param("sssss", $username, $email, $passwordHash, $trialStart, $trialEnd);
        
        if ($stmt->execute()) {
            $userId = $this->conn->insert_id;
            
            // Set session
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['logged_in'] = true;
            
            return [
                'success' => true, 
                'message' => 'Registration successful',
                'user_id' => $userId
            ];
        } else {
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    public function login($email, $password) {
        $email = $this->db->escape($email);
        
        $query = "SELECT id, username, password_hash, account_status, trial_end 
                 FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['logged_in'] = true;
                
                // Check if user has active plan
                $planQuery = "SELECT current_plan FROM users WHERE id = ?";
                $stmt = $this->conn->prepare($planQuery);
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $planResult = $stmt->get_result();
                $userPlan = $planResult->fetch_assoc();
                
                $hasPlan = !empty($userPlan['current_plan']) && $userPlan['current_plan'] !== 'basic';
                
                return [
                    'success' => true,
                    'user' => $user,
                    'has_plan' => $hasPlan
                ];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    public function isAuthenticated() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $userId = $_SESSION['user_id'];
        $query = "SELECT id, username, email, account_status, current_plan, 
                 trial_start, trial_end, subscription_start, subscription_end 
                 FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        return true;
    }
    
    public function updateUserPlan($userId, $planId) {
        // Get plan details
        $planQuery = "SELECT * FROM subscription_plans WHERE id = ?";
        $stmt = $this->conn->prepare($planQuery);
        $stmt->bind_param("i", $planId);
        $stmt->execute();
        $planResult = $stmt->get_result();
        $plan = $planResult->fetch_assoc();
        
        if (!$plan) {
            return ['success' => false, 'message' => 'Invalid plan'];
        }
        
        // Calculate subscription dates
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+1 month'));
        
        // Update user
        $updateQuery = "UPDATE users SET 
                        current_plan = ?,
                        account_status = 'active',
                        subscription_start = ?,
                        subscription_end = ?
                        WHERE id = ?";
        
        $stmt = $this->conn->prepare($updateQuery);
        $planName = $plan['plan_name'];
        $stmt->bind_param("sssi", $planName, $startDate, $endDate, $userId);
        
        if ($stmt->execute()) {
            // this is a Subsctiorion records 
            $subQuery = "INSERT INTO user_subscriptions 
                        (user_id, plan_id, start_date, end_date, payment_status) 
                        VALUES (?, ?, ?, ?, 'pending')";
            $stmt = $this->conn->prepare($subQuery);
            $stmt->bind_param("iiss", $userId, $planId, $startDate, $endDate);
            $stmt->execute();
            
            return ['success' => true, 'message' => 'Plan updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update plan'];
    }
}
?>