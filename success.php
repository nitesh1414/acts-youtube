<?php
/**
 * Private video page. Requires a valid access token (URL parameter or the
 * access cookie set after a verified payment).
 */
require_once __DIR__ . '/config.php';

$settings = app()->settings->all();
$token = query_str('t');
$payment = null;

if ($token !== '') {
    $candidate = app()->payments->findByAccessToken($token);
    if ($candidate && Payments::accessIsValid($candidate)) {
        $payment = $candidate;
        // Refresh the session + cookie so the token survives a page reload.
        set_access_cookie((string) $candidate['access_token'], strtotime((string) $candidate['access_expires_at']));
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['access_payment_id'] = (int) $candidate['id'];
            $_SESSION['access_token'] = (string) $candidate['access_token'];
        }
        app()->payments->logEvent($candidate['id'], 'access.viewed', 'Video page opened with token', [
            'ip' => client_ip(),
        ]);
    }
}

if ($payment === null) {
    $payment = current_access_payment();
}

if ($payment === null) {
    render('site/locked', [
        'pageTitle' => 'This video is locked · ' . $settings['site_name'],
        'bodyClass' => 'site-locked',
    ]);
    return;
}

render('site/success', [
    'pageTitle'       => 'Your private video · ' . $settings['site_name'],
    'metaDescription' => 'Watch the private video you unlocked.',
    'bodyClass'       => 'site-success',
    'payment'         => $payment,
]);
