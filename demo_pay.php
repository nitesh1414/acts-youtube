<?php
/**
 * Demo checkout (only active while "Demo mode" is enabled in site settings).
 * Lets you exercise the full payment → access flow without Razorpay keys.
 */
require_once __DIR__ . '/config.php';

$settings = app()->settings->all();

if ((int) $settings['demo_mode'] !== 1) {
    abort(404, 'Demo mode is disabled. Enable it in the admin panel to test payments.');
}

$orderId = is_post() ? post_str('order') : query_str('order');
$payment = $orderId !== '' ? app()->payments->findByOrderId($orderId) : null;

if (!$payment) {
    abort(404, 'This demo order could not be found or has expired.');
}

if (is_post()) {
    csrf_verify();

    if ($payment['status'] === Payments::STATUS_SUCCESS) {
        flash('info', 'This demo payment was already completed.');
        redirect('success.php');
    }

    $action = post_str('action');

    if ($action === 'success') {
        $demoPaymentId = 'pay_demo_' . strtolower(bin2hex(random_bytes(6)));
        app()->payments->markSuccess($payment['id'], [
            'payment_id' => $demoPaymentId,
            'signature'  => 'demo-signature',
            'method'     => 'demo',
            'notes'      => 'Demo mode payment',
        ]);
        app()->payments->logEvent($payment['id'], 'payment.captured', 'Demo payment simulated successfully', [
            'payment_id' => $demoPaymentId,
        ]);

        $fresh = app()->payments->find($payment['id']);
        $access = issue_access($fresh);

        flash('success', 'Demo payment completed — welcome!');
        redirect('success.php?t=' . urlencode($access['token']));
    }

    if ($action === 'fail') {
        app()->payments->markFailed($payment['id'], 'Demo payment cancelled');
        app()->payments->logEvent($payment['id'], 'payment.failed', 'Demo payment marked as failed');
        flash('danger', 'Demo payment cancelled. No access was granted.');
    }

    redirect('');
}

render('site/demo-checkout', [
    'pageTitle'       => 'Demo checkout · ' . $settings['site_name'],
    'metaDescription' => 'Simulated checkout for testing.',
    'bodyClass'       => 'site-demo',
    'payment'         => $payment,
]);
