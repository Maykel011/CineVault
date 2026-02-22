<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/auth.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

// Validate required fields
$required = ['username', 'email', 'password', 'confirm_password'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit();
    }
}

// Check if passwords match
if ($input['password'] !== $input['confirm_password']) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit();
}

// Create auth instance and register
$auth = new Auth();
$result = $auth->register($input);

// Return response
echo json_encode($result);
?>