<?php
/**
 * Access recovery: a buyer proves ownership with the mobile number + name used
 * at checkout and receives a fresh access link. The e-mail is optional, because
 * it is optional at checkout too.
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

        $name = post_str('name');
        $email = strtolower(post_str('email'));
        $mobile = post_str('mobile');

        if (text_length($name) < 2) {
            $error = 'Enter the name you used at checkout.';
        } elseif (!valid_mobile($mobile)) {
            $error = 'Enter the mobile number you used at checkout.';
        } elseif ($email !== '' && !valid_email($email)) {
            $error = 'That e-mail address does not look right — leave it blank if you did not give one.';
        } else {
            $payment = app()->payments->findForRestore($name, $mobile, $email);

            if (!$payment) {
                app_log('notice', 'Access restore failed', ['mobile' => normalize_mobile($mobile), 'ip' => $ip]);
                $error = 'We could not find a successful payment for that mobile number and name. '
                    . 'Please check them — the name only has to match the one you paid with.';
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
        flash_input([
            'name'   => post_str('name'),
            'email'  => post_str('email'),
            'mobile' => post_str('mobile'),
        ]);
    }
}

render('site/restore', [
    'pageTitle'       => 'Restore access · ' . $settings['site_name'],
    'metaDescription' => 'Restore your private video access using your mobile number and name.',
    'bodyClass'       => 'site-restore',
    'error'           => $error,
]);
