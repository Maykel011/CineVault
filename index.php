<?php
require_once 'includes/config.php';

// Redirect to appropriate page
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/login.php');
}
exit();
?>