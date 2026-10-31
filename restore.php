<?php
/**
 * Access recovery: a buyer proves ownership with the e-mail + mobile number
 * used at checkout and receives a fresh access link.
 */
require_once __DIR__ . '/config.php';

$settings = app()->settings->all();
$error = '';

if (is_post()) {
    csrf_verify();

    $ip = client_ip();
    if (app()->throttle->tooMany('restore_access', $ip, 6, 900)) {
        $wait = (int) ceil(app()->throttle->retryAfter('restore_access', $ip, 900) / 60);
        $error = 'Too many attempts. Please try again in about ' . max(1, $wait) . ' minute(s).';
    } else {
        app()->throttle->hit('restore_access', $ip);

        $email = post_str('email');
        $mobile = post_str('mobile');

        if (!valid_email($email)) {
            $error = 'Enter the e-mail address you used at checkout.';
        } elseif (!valid_mobile($mobile)) {
            $error = 'Enter the mobile number you used at checkout.';
        } else {
            $payment = app()->payments->findByEmailAndMobile($email, $mobile);

            if (!$payment) {
                app_log('notice', 'Access restore failed', ['email' => $email, 'ip' => $ip]);
                $error = 'We could not find a successful payment with those details. Please double-check them or contact support.';
            } elseif (!Payments::accessIsValid($payment) && !empty($payment['access_expires_at'])) {
                $error = 'Your access period has ended. Please contact support to renew it.';
            } else {
                $access = issue_access($payment);
                app()->throttle->clear('restore_access', $ip);
                clear_old_input();
                flash('success', 'Welcome back! Your access link has been restored.');
                redirect('success.php?t=' . urlencode($access['token']));
            }
        }
    }

    if ($error !== '') {
        flash_input(['email' => post_str('email'), 'mobile' => post_str('mobile')]);
    }
}

render('site/restore', [
    'pageTitle'       => 'Restore access · ' . $settings['site_name'],
    'metaDescription' => 'Restore your private video access using your e-mail and mobile number.',
    'bodyClass'       => 'site-restore',
    'error'           => $error,
]);
