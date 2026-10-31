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
$currency = (string) $settings['currency'];
$demoMode = (int) $settings['demo_mode'] === 1;

// -----------------------------------------------------------------------------
// What is being bought? The browser only sends an id — the price always comes
// from the database, so a tampered request can never lower it.
// -----------------------------------------------------------------------------
$itemType = isset($input['item_type']) && is_scalar($input['item_type'])
    ? strtolower(trim((string) $input['item_type']))
    : '';
$itemId = isset($input['item_id']) && is_scalar($input['item_id']) ? (int) $input['item_id'] : 0;

if ($itemType !== '' && $itemType !== 'single') {
    $product = catalog_product($itemType, $itemId, true);
    if ($product === null) {
        json_response(['error' => 'That video or bundle is no longer available. Please pick another one.'], 404);
    }
} else {
    // Older page/bookmark: fall back to the first item on sale.
    $storefront = catalog_storefront();
    $product = catalog_selected_product($storefront, '');
    if ($product === null) {
        json_response(['error' => 'Nothing is on sale yet. Please check back soon.'], 503);
    }
}

$amount = (float) $product['price'];
$productType = (string) $product['type'];
$productId = (int) $product['id'];
$productName = (string) $product['name'];

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
        'amount'       => $amount,
        'currency'     => $currency,
        'product_type' => $productType,
        'product_id'   => $productId,
        'product_name' => $productName,
    ]);
} catch (DatabaseException $e) {
    app_log('error', 'Could not create payment record: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'missing' => Schema::missing(app()->db),
    ]);
    json_response(['error' => database_error_hint($e, $ref), 'code' => 'database_error', 'ref' => $ref], 500);
} catch (Throwable $e) {
    app_log('error', 'Could not create payment record: ' . $e->getMessage());
    json_response(['error' => 'We could not start your payment. Please try again.'], 500);
}

app()->payments->logEvent($paymentId, 'order.created', 'Checkout started', [
    'amount'  => $amount,
    'product' => $productType . ':' . $productId,
]);

// -----------------------------------------------------------------------------
// Demo mode: return a link to the simulated checkout.
// -----------------------------------------------------------------------------
if ($demoMode) {
    $orderId = 'demo_' . strtolower(bin2hex(random_bytes(8)));
    try {
        app()->db->update('payments', ['order_id' => $orderId, 'updated_at' => now()], 'id = ?', [$paymentId]);
    } catch (DatabaseException $e) {
        app_log('error', 'Could not attach the demo order id: ' . $e->getMessage());
        json_response(['error' => database_error_hint($e, $ref), 'code' => 'database_error', 'ref' => $ref], 500);
    }

    json_response([
        'demo'          => true,
        'order_id'      => $orderId,
        'product'       => ['type' => $productType, 'id' => $productId, 'name' => $productName],
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

try {
    app()->db->update('payments', ['order_id' => $order['id'], 'updated_at' => now()], 'id = ?', [$paymentId]);
} catch (DatabaseException $e) {
    app_log('error', 'Could not store the Razorpay order id: ' . $e->getMessage());
    json_response(['error' => database_error_hint($e, $ref), 'code' => 'database_error', 'ref' => $ref], 500);
}
app()->payments->logEvent($paymentId, 'order.created.razorpay', 'Razorpay order ' . $order['id']);

json_response([
    'order_id'      => $order['id'],
    'payment_db_id' => $paymentId,
    'product'       => ['type' => $productType, 'id' => $productId, 'name' => $productName],
    'amount'        => (int) $order['amount'],
    'currency'      => isset($order['currency']) ? $order['currency'] : $currency,
    'key_id'        => $razorpay->keyId(),
    'name'          => $settings['site_name'],
    'description'   => 'Access to ' . $productName,
]);
