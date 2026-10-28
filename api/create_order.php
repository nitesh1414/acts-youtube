<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['name'], $data['email'], $data['mobile'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$name = trim($data['name']);
$email = trim($data['email']);
$mobile = trim($data['mobile']);

$settings = getSettings($pdo);

if (empty($settings['razorpay_key_id']) || empty($settings['razorpay_key_secret'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Razorpay keys not configured in admin panel']);
    exit;
}

// Insert pending payment into database first
$stmt = $pdo->prepare("INSERT INTO payments (user_name, user_email, user_mobile, amount, status) VALUES (?, ?, ?, ?, 'pending')");
$stmt->execute([$name, $email, $mobile, $settings['amount']]);
$paymentDbId = $pdo->lastInsertId();

// Create Razorpay Order using cURL
$amountInPaise = (int)(floatval($settings['amount']) * 100);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $settings['razorpay_key_id'] . ':' . $settings['razorpay_key_secret']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'amount' => $amountInPaise,
    'currency' => 'INR',
    'receipt' => 'receipt_' . $paymentDbId,
    'payment_capture' => 1
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300 || isset($result['error'])) {
    // Update payment as failed
    $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE id = ?");
    $stmt->execute([$paymentDbId]);
    
    $errorMsg = $result['error']['description'] ?? 'Failed to create order';
    http_response_code(500);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

// Update payment with order_id
$stmt = $pdo->prepare("UPDATE payments SET order_id = ? WHERE id = ?");
$stmt->execute([$result['id'], $paymentDbId]);

echo json_encode([
    'order_id' => $result['id'],
    'amount' => $amountInPaise,
    'payment_db_id' => $paymentDbId
]);
?>
