<?php
/**
 * POST /api/create_order.php
 * Validates the buyer details, stores a pending payment and creates a Razorpay
 * order. In demo mode no Razorpay call is made.
 */
require_once __DIR__ . '/../config.php';

require_method('POST');

if (!csrf_valid(csrf_token_from_request())) {
    json_response(['error' => 'Your session expired. Please reload the page and try again.'], 419);
}

$input = json_input();
$name   = isset($input['name']) && is_scalar($input['name']) ? trim((string) $input['name']) : '';
$email  = isset($input['email']) && is_scalar($input['email']) ? trim((string) $input['email']) : '';
$mobile = isset($input['mobile']) && is_scalar($input['mobile']) ? trim((string) $input['mobile']) : '';

$errors = [];
if (text_length($name) < 2 || text_length($name) > 120) {
    $errors['name'] = 'Please enter your full name.';
}
if (!valid_email($email)) {
    $errors['email'] = 'Please enter a valid e-mail address.';
}
if (!valid_mobile($mobile)) {
    $errors['mobile'] = 'Please enter a valid mobile number.';
}
if (!empty($errors)) {
    json_response(['error' => reset($errors), 'errors' => $errors], 422);
}

// Rate limiting (per IP).
$ip = client_ip();
if (app()->throttle->tooMany('create_order', $ip, 10, 600)) {
    json_response(['error' => 'Too many checkout attempts. Please wait a few minutes and try again.'], 429);
}
app()->throttle->hit('create_order', $ip);

$settings = app()->settings->all();
$amount = (float) $settings['amount'];
$currency = (string) $settings['currency'];
$demoMode = (int) $settings['demo_mode'] === 1;

if ($amount <= 0) {
    json_response(['error' => 'The price is not configured yet. Please contact support.'], 503);
}

$razorpay = app()->razorpay();
if (!$demoMode && $razorpay === null) {
    json_response(['error' => 'Payments are not configured yet. Please contact support.'], 503);
}

try {
    $paymentId = app()->payments->create([
        'user_name'   => $name,
        'user_email'  => $email,
        'user_mobile' => $mobile,
        'amount'      => $amount,
        'currency'    => $currency,
    ]);
} catch (Throwable $e) {
    app_log('error', 'Could not create payment record: ' . $e->getMessage());
    json_response(['error' => 'We could not start your payment. Please try again.'], 500);
}

app()->payments->logEvent($paymentId, 'order.created', 'Checkout started', ['amount' => $amount]);

// -----------------------------------------------------------------------------
// Demo mode: return a link to the simulated checkout.
// -----------------------------------------------------------------------------
if ($demoMode) {
    $orderId = 'demo_' . strtolower(bin2hex(random_bytes(8)));
    app()->db->update('payments', ['order_id' => $orderId, 'updated_at' => now()], 'id = ?', [$paymentId]);

    json_response([
        'demo'          => true,
        'order_id'      => $orderId,
        'payment_db_id' => $paymentId,
        'amount'        => (int) round($amount * 100),
        'currency'      => $currency,
        'demo_url'      => url('demo_pay.php?order=' . urlencode($orderId)),
    ]);
}

// -----------------------------------------------------------------------------
// Live mode: create the Razorpay order.
// -----------------------------------------------------------------------------
try {
    $order = $razorpay->createOrder((int) round($amount * 100), 'rcpt_' . $paymentId, [
        'payment_db_id' => (string) $paymentId,
        'customer'      => $email,
    ]);
} catch (RazorpayException $e) {
    app()->payments->markFailed($paymentId, 'Order creation failed');
    app()->payments->logEvent($paymentId, 'order.failed', $e->getMessage());
    json_response(['error' => $e->getMessage()], 502);
}

app()->db->update('payments', ['order_id' => $order['id'], 'updated_at' => now()], 'id = ?', [$paymentId]);
app()->payments->logEvent($paymentId, 'order.created.razorpay', 'Razorpay order ' . $order['id']);

json_response([
    'order_id'      => $order['id'],
    'payment_db_id' => $paymentId,
    'amount'        => (int) $order['amount'],
    'currency'      => isset($order['currency']) ? $order['currency'] : $currency,
    'key_id'        => $razorpay->keyId(),
    'name'          => $settings['site_name'],
    'description'   => 'Access to ' . $settings['logo_text'],
]);
