<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cinevault_db');

// Site configuration
define('SITE_NAME', 'CineVault');
define('SITE_URL', 'http://localhost/cinevault');
define('TRIAL_DAYS', 10);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Manila');
?>