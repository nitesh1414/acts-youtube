<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'], $data['payment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$settings = getSettings($pdo);

$generated_signature = hash_hmac('sha256', $data['razorpay_order_id'] . '|' . $data['razorpay_payment_id'], $settings['razorpay_key_secret']);

if ($generated_signature === $data['razorpay_signature']) {
    // Payment successful
    $stmt = $pdo->prepare("UPDATE payments SET status = 'success', payment_id = ? WHERE id = ?");
    $stmt->execute([$data['razorpay_payment_id'], $data['payment_id']]);
    echo json_encode(['success' => true]);
} else {
    $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE id = ?");
    $stmt->execute([$data['payment_id']]);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment signature']);
}
?>
