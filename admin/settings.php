<?php
/**
 * Site settings, including the logo upload (replaces the old "Logo URL" field).
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$errors = [];
$form = app()->settings->all();

if (is_post()) {
    csrf_verify();

    $result = app()->settings->save($_POST, $_FILES);

    if ($result['ok']) {
        app()->forgetRazorpay();
        app_log('info', 'Settings updated', ['admin_id' => $admin['id']]);
        clear_old_input();
        flash('success', $result['message']);
        redirect('admin/settings.php');
    }

    $errors = $result['errors'];
    // Keep what the admin typed (secrets are intentionally not echoed back).
    $form = array_merge($form, array_intersect_key($_POST, array_flip([
        'site_name', 'logo_text', 'tagline', 'amount', 'access_days',
        'youtube_video_url', 'youtube_channel_url', 'support_email',
        'support_phone', 'razorpay_key_id', 'theme_color',
    ])));
    $form['demo_mode'] = !empty($_POST['demo_mode']) ? 1 : 0;
    // Errors are rendered inline below — no flash message needed.
}

render('admin/settings', [
    'pageTitle' => 'Site settings',
    'activeNav' => 'settings.php',
    'admin'     => $admin,
    'settings'  => app()->settings->all(),
    'form'      => $form,
    'errors'    => $errors,
]);
