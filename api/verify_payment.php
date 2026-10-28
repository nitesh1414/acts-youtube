<?php
/**
 * POST /api/verify_payment.php
 * Verifies the Razorpay signature (and the payment with the API when possible),
 * marks the record as paid and issues the access token.
 */
require_once __DIR__ . '/../config.php';

require_method('POST');

if (!csrf_valid(csrf_token_from_request())) {
    json_response(['error' => 'Your session expired. Please reload the page and try again.'], 419);
}

$input = json_input();
$pick = function ($key) use ($input) {
    return isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : '';
};
$orderId    = $pick('razorpay_order_id');
$razorpayId = $pick('razorpay_payment_id');
$signature  = $pick('razorpay_signature');
$dbId       = (int) $pick('payment_db_id');

if ($orderId === '' || $razorpayId === '' || $signature === '' || $dbId <= 0) {
    json_response(['error' => 'Missing payment details.'], 400);
}

$payment = app()->payments->find($dbId);
if (!$payment) {
    json_response(['error' => 'Payment record not found.'], 404);
}

// The order in our database must match the order that was paid.
if (!hash_equals((string) $payment['order_id'], $orderId)) {
    app()->payments->logEvent($payment['id'], 'verify.mismatch', 'Order id does not match the stored payment');
    json_response(['error' => 'Payment verification failed (order mismatch).'], 400);
}

// Already verified? Return the existing access link (idempotent) — but only to
// a caller that can present the same signature (a genuine retry).
if ($payment['status'] === Payments::STATUS_SUCCESS) {
    $storedSignature = (string) $payment['signature'];
    if ($storedSignature !== '' && !hash_equals($storedSignature, $signature)) {
        json_response(['error' => 'This payment was already verified on another session. Please use “Restore access”.'], 409);
    }
    if (!Payments::accessIsValid($payment)) {
        $access = issue_access($payment);
        $token = $access['token'];
    } else {
        $token = (string) $payment['access_token'];
    }
    json_response(['success' => true, 'redirect' => url('success.php?t=' . urlencode($token))]);
}

$razorpay = app()->razorpay();
if ($razorpay === null) {
    json_response(['error' => 'Payments are not configured. Please contact support.'], 503);
}

// 1. Signature check — the authoritative offline proof of payment.
if (!$razorpay->verifyPaymentSignature($orderId, $razorpayId, $signature)) {
    app()->payments->markFailed($payment['id'], 'Signature verification failed');
    app()->payments->logEvent($payment['id'], 'verify.failed', 'Invalid Razorpay signature');
    json_response(['error' => 'Payment signature verification failed. If money was deducted, contact support with your payment id.'], 400);
}

// 2. Best-effort API check (amount / status / order). Network issues should not
//    block a legitimately signed payment, so failures here are only logged.
try {
    $entity = $razorpay->fetchPayment($razorpayId);
    $expectedAmount = (int) round(((float) $payment['amount']) * 100);
    $status = isset($entity['status']) ? (string) $entity['status'] : '';
    $entityOrder = isset($entity['order_id']) ? (string) $entity['order_id'] : '';
    $entityAmount = isset($entity['amount']) ? (int) $entity['amount'] : 0;

    if (!in_array($status, ['captured', 'authorized'], true)) {
        app()->payments->markFailed($payment['id'], 'Razorpay status: ' . $status);
        app()->payments->logEvent($payment['id'], 'verify.failed', 'Payment not captured (' . $status . ')');
        json_response(['error' => 'The payment has not been captured yet. Please contact support.'], 400);
    }
    if ($entityOrder !== '' && !hash_equals((string) $payment['order_id'], $entityOrder)) {
        app()->payments->logEvent($payment['id'], 'verify.warning', 'Order id mismatch from Razorpay API');
    }
    if ($entityAmount > 0 && $entityAmount !== $expectedAmount) {
        app()->payments->markFailed($payment['id'], 'Amount mismatch');
        app()->payments->logEvent($payment['id'], 'verify.failed', 'Amount mismatch: expected ' . $expectedAmount . ', got ' . $entityAmount);
        json_response(['error' => 'The paid amount does not match the price. Please contact support.'], 400);
    }
} catch (RazorpayException $e) {
    app_log('warning', 'Razorpay verification lookup failed: ' . $e->getMessage());
    app()->payments->logEvent($payment['id'], 'verify.warning', 'API lookup failed; relying on signature');
}

// 3. Mark as paid and grant access.
app()->payments->markSuccess($payment['id'], [
    'payment_id' => $razorpayId,
    'signature'  => $signature,
    'method'     => isset($entity['method']) ? (string) $entity['method'] : null,
]);
app()->payments->logEvent($payment['id'], 'payment.captured', 'Payment verified successfully', [
    'payment_id' => $razorpayId,
]);

$fresh = app()->payments->find($payment['id']);
$access = issue_access($fresh);

json_response([
    'success'  => true,
    'redirect' => url('success.php?t=' . urlencode($access['token'])),
]);
