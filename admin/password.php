<?php
/**
 * Change the signed-in admin's password.
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$errors = [];

if (is_post()) {
    csrf_verify();

    $result = app()->auth->changePassword(
        (int) $admin['id'],
        (string) (isset($_POST['current_password']) ? $_POST['current_password'] : ''),
        (string) (isset($_POST['new_password']) ? $_POST['new_password'] : ''),
        (string) (isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '')
    );

    if ($result['ok']) {
        flash('success', 'Your password has been updated.');
        redirect('admin/password.php');
    }

    $errors['general'] = $result['error'];
}

render('admin/password', [
    'pageTitle' => 'Change password',
    'activeNav' => 'password.php',
    'admin'     => $admin,
    'errors'    => $errors,
]);
