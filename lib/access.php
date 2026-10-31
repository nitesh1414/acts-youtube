<?php
/**
 * Buyer access control: the paid video page is only reachable with a valid
 * access token issued after a verified payment.
 */

/**
 * Grant access to a paid payment, remember it in the session and set the
 * long-lived cookie so the buyer can come back within the access window.
 *
 * @param array<string, mixed> $payment
 * @param int|null             $days
 * @return array{token:string, expires_at:string}
 */
function issue_access(array $payment, $days = null)
{
    $app = app();
    if ($days === null) {
        $days = $app->settings->accessDays();
    }

    $access = $app->payments->grantAccess((int) $payment['id'], $days);

    if (session_is_active()) {
        $_SESSION['access_payment_id'] = (int) $payment['id'];
        $_SESSION['access_token'] = $access['token'];
    }

    set_access_cookie($access['token'], $access['expires_at'] === null ? null : strtotime($access['expires_at']));

    $app->payments->logEvent(
        $payment['id'],
        'access.granted',
        $access['expires_at'] === null ? 'Lifetime access granted' : 'Access valid until ' . $access['expires_at']
    );

    return $access;
}

/**
 * Store the access token cookie. A null expiry means lifetime access: browsers
 * still cap cookies, so use the longest lifetime they accept and refresh it on
 * every visit.
 */
function set_access_cookie($token, $expiresAt = null)
{
    if (headers_sent()) {
        return;
    }
    setcookie(ACCESS_COOKIE, (string) $token, [
        'expires'  => $expiresAt === null ? time() + 400 * 86400 : (int) $expiresAt,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[ACCESS_COOKIE] = (string) $token;
}

/**
 * Remove the access token cookie.
 */
function clear_access_cookie()
{
    if (!headers_sent()) {
        setcookie(ACCESS_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE[ACCESS_COOKIE]);
    if (session_is_active()) {
        unset($_SESSION['access_payment_id'], $_SESSION['access_token']);
    }
}

/**
 * The payment the current visitor has paid access to, or null.
 *
 * @return array<string, mixed>|null
 */
function current_access_payment()
{
    $app = app();
    if ($app === null) {
        return null;
    }

    $candidates = [];

    if (session_is_active() && !empty($_SESSION['access_token'])) {
        $candidates[] = (string) $_SESSION['access_token'];
    }
    if (!empty($_COOKIE[ACCESS_COOKIE])) {
        $candidates[] = (string) $_COOKIE[ACCESS_COOKIE];
    }

    foreach (array_unique($candidates) as $token) {
        $payment = $app->payments->findByAccessToken($token);
        if ($payment && Payments::accessIsValid($payment)) {
            if (session_is_active()) {
                $_SESSION['access_payment_id'] = (int) $payment['id'];
                $_SESSION['access_token'] = $token;
            }
            return $payment;
        }
    }

    return null;
}

/**
 * Days remaining on an access pass, or null when it never expires.
 *
 * @return int|null
 */
function access_days_left(array $payment)
{
    if (empty($payment['access_expires_at'])) {
        return null;
    }
    $remaining = strtotime((string) $payment['access_expires_at']) - time();
    return $remaining <= 0 ? 0 : (int) ceil($remaining / 86400);
}
