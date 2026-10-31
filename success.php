<?php
/**
 * The buyer's private library: every video their payment unlocked, playable
 * from the link in the URL (`t`) or from the access cookie on this device.
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
        set_access_cookie(
            (string) $candidate['access_token'],
            empty($candidate['access_expires_at']) ? null : strtotime((string) $candidate['access_expires_at'])
        );
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['access_payment_id'] = (int) $candidate['id'];
            $_SESSION['access_token'] = (string) $candidate['access_token'];
        }
        app()->payments->logEvent($candidate['id'], 'access.viewed', 'Library opened with token', [
            'ip' => client_ip(),
        ]);
    }
}

if ($payment === null) {
    $payment = current_access_payment();
}

if ($payment === null) {
    render('site/locked', [
        'pageTitle' => 'Your library is locked · ' . $settings['site_name'],
        'bodyClass' => 'site-locked',
    ]);
    return;
}

// What this purchase unlocked (snapshot taken at payment time)…
$purchased = catalog_videos_for_payment($payment);
// …and the whole library: every earlier purchase by the same e-mail + mobile.
$videos = catalog_library_for_payment($payment);
$purchasedIds = [];
foreach ($purchased as $video) {
    $purchasedIds[] = (int) $video['id'];
}

$current = null;
$wantedId = query_int('v', 0);
foreach ($videos as $video) {
    if ($current === null) {
        $current = $video; // default to the first video in the set
    }
    if ($wantedId > 0 && (int) $video['id'] === $wantedId) {
        $current = $video;
    }
}

render('site/success', [
    'pageTitle'       => (count($videos) > 1 ? 'Your videos' : 'Your video') . ' · ' . $settings['site_name'],
    'metaDescription' => 'Watch the videos you unlocked.',
    'bodyClass'       => 'site-success',
    'payment'         => $payment,
    'videos'          => $videos,
    'purchased'       => $purchased,
    'purchasedIds'    => $purchasedIds,
    'current'         => $current,
    'token'           => (string) $payment['access_token'],
]);
