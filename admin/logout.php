<?php
/**
 * Sign out (POST only, CSRF protected).
 */
require_once __DIR__ . '/../config.php';

if (!is_post()) {
    redirect('admin/index.php');
}

csrf_verify();
app()->auth->logout();
flash('success', 'You have been signed out.');
redirect('admin/login.php');
