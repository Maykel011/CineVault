<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

if (empty($input['email']) || empty($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

$auth = new Auth();
$result = $auth->login($input['email'], $input['password']);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect' => $result['has_plan'] ? 'dashboard.php' : 'plans.php'
    ]);
} else {
    echo json_encode($result);
}
?>