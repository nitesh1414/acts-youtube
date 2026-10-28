<?php
/**
 * Admin accounts: list, create, enable/disable and delete.
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$errors = [];

if (is_post()) {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'create') {
        $result = app()->auth->create(
            post_str('username'),
            post_str('name'),
            post_str('email'),
            (string) (isset($_POST['password']) ? $_POST['password'] : ''),
            (string) (isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '')
        );

        if ($result['ok']) {
            clear_old_input();
            flash('success', 'Admin account created.');
            redirect('admin/admins.php');
        }
        $errors['general'] = $result['error'];
        flash_input([
            'username' => post_str('username'),
            'name'     => post_str('name'),
            'email'    => post_str('email'),
        ]);
    } elseif ($action === 'toggle') {
        $targetId = (int) post_str('id');
        $activate = post_str('active') === '1';
        $result = app()->auth->setActive($targetId, $activate, (int) $admin['id']);
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Account updated.' : $result['error']);
        redirect('admin/admins.php');
    } elseif ($action === 'delete') {
        $targetId = (int) post_str('id');
        $result = app()->auth->delete($targetId, (int) $admin['id']);
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Account deleted.' : $result['error']);
        redirect('admin/admins.php');
    } else {
        flash('danger', 'Unknown action.');
        redirect('admin/admins.php');
    }
}

render('admin/admins', [
    'pageTitle' => 'Admin users',
    'activeNav' => 'admins.php',
    'admin'     => $admin,
    'admins'    => app()->auth->admins(),
    'errors'    => $errors,
]);
