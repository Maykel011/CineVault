<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

if (empty($input['plan_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please select a plan']);
    exit();
}

// Validate payment details (in real app, you'd process payment here)
$paymentFields = ['card_number', 'expiry', 'cvv', 'card_name'];
foreach ($paymentFields as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => 'Please complete all payment details']);
        exit();
    }
}

$auth = new Auth();
$result = $auth->updateUserPlan($_SESSION['user_id'], $input['plan_id']);

if ($result['success']) {
    // Log payment (simplified)
    $db = getDB();
    $paymentQuery = "INSERT INTO payment_history (user_id, amount, payment_method, transaction_status) 
                     SELECT ?, monthly_price, 'credit_card', 'completed' 
                     FROM subscription_plans WHERE id = ?";
    $stmt = $db->prepare($paymentQuery);
    $stmt->bind_param("ii", $_SESSION['user_id'], $input['plan_id']);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Subscription successful! Welcome to CineVault!',
        'redirect' => 'dashboard.php'
    ]);
} else {
    echo json_encode($result);
}
?>