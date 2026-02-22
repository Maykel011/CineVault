<?php
require_once '../includes/auth.php';

$auth = new Auth();
$auth->logout();

header('Location: ../pages/login.php?logged_out=true');
exit();
?>