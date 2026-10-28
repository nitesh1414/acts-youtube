<?php
/**
 * POST /api/webhook.php
 * Razorpay webhook endpoint (server-to-server). Confirms payments even when the
 * buyer closes the tab before the browser-side verification runs.
 *
 * Configure this URL in Razorpay → Settings → Webhooks and subscribe to
 * `payment.captured`, `payment.failed` and `order.paid`.
 */
require_once __DIR__ . '/../config.php';

require_method('POST');

$settings = app()->settings->all();
$secret = trim((string) $settings['razorpay_webhook_secret']);

if ($secret === '') {
    app_log('warning', 'Webhook received but no webhook secret is configured');
    json_response(['error' => 'Webhook secret is not configured.'], 503);
}

$rawBody = raw_input();
$signature = isset($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']) ? (string) $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] : '';

if (!RazorpayClient::verifyWebhookSignature($rawBody, $signature, $secret)) {
    app_log('warning', 'Webhook signature verification failed', ['ip' => client_ip()]);
    json_response(['error' => 'Invalid signature.'], 400);
}

$payload = json_decode((string) $rawBody, true);
if (!is_array($payload) || empty($payload['event'])) {
    json_response(['error' => 'Invalid payload.'], 400);
}

$event = (string) $payload['event'];
$entity = [];
foreach (['payment', 'order'] as $type) {
    if (!empty($payload['payload'][$type]['entity']) && is_array($payload['payload'][$type]['entity'])) {
        $entity = $payload['payload'][$type]['entity'];
        break;
    }
}

$orderId = '';
foreach (['order_id', 'id'] as $key) {
    if (!empty($entity[$key]) && is_string($entity[$key])) {
        $orderId = $entity[$key];
        break;
    }
}
if ($orderId === '' && $event === 'order.paid' && !empty($payload['payload']['order']['entity']['id'])) {
    $orderId = (string) $payload['payload']['order']['entity']['id'];
}

$payment = $orderId !== '' ? app()->payments->findByOrderId($orderId) : null;

// Fall back to the payment_db_id note set when the order was created.
if (!$payment && !empty($entity['notes']['payment_db_id'])) {
    $payment = app()->payments->find((int) $entity['notes']['payment_db_id']);
}

if (!$payment) {
    app_log('warning', 'Webhook for unknown order', ['event' => $event, 'order' => $orderId]);
    json_response(['status' => 'ignored', 'reason' => 'unknown order']);
}

app()->payments->logEvent($payment['id'], 'webhook.' . $event, 'Webhook received', [
    'order_id' => $orderId,
]);

switch ($event) {
    case 'payment.captured':
    case 'order.paid':
        if ($payment['status'] !== Payments::STATUS_SUCCESS) {
            app()->payments->markSuccess($payment['id'], [
                'payment_id' => isset($entity['id']) ? (string) $entity['id'] : null,
                'method'     => isset($entity['method']) ? (string) $entity['method'] : null,
                'notes'      => 'Confirmed via webhook',
            ]);
            $fresh = app()->payments->find($payment['id']);
            issue_access($fresh);
            app()->payments->logEvent($payment['id'], 'payment.captured.webhook', 'Payment confirmed by webhook');
        }
        break;

    case 'payment.failed':
        if ($payment['status'] !== Payments::STATUS_SUCCESS) {
            app()->payments->markFailed($payment['id'], 'Webhook: payment failed');
        }
        break;

    case 'order.expired':
        if ($payment['status'] === Payments::STATUS_PENDING) {
            app()->payments->markFailed($payment['id'], 'Webhook: order expired');
        }
        break;

    default:
        // Acknowledge unhandled events so Razorpay stops retrying.
        break;
}

json_response(['status' => 'ok', 'event' => $event]);
