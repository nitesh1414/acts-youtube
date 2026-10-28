<?php
/**
 * Admin sign-in.
 */
require_once __DIR__ . '/../config.php';

if (app()->auth->check()) {
    redirect('admin/index.php');
}

/**
 * Only allow redirects inside this application.
 */
function admin_safe_next($next)
{
    $next = trim((string) $next);
    if ($next === '' || strpos($next, '//') !== false || strpos($next, '\\') !== false) {
        return '';
    }
    if (!preg_match('#^/[A-Za-z0-9._/?=&\-]*$#', $next)) {
        return '';
    }
    return $next;
}

$error = '';
$next = admin_safe_next(is_post() ? post_str('next') : query_str('next'));

if (is_post()) {
    csrf_verify();

    $result = app()->auth->attempt(post_str('username'), (string) (isset($_POST['password']) ? $_POST['password'] : ''));

    if ($result['ok']) {
        flash('success', 'Signed in as ' . $result['admin']['name'] . '.');
        if ($next !== '') {
            redirect($next);
        }
        redirect('admin/index.php');
    }

    $error = $result['error'];
    flash_input(['username' => post_str('username')]);
}

render('admin/login', [
    'pageTitle' => 'Admin sign in · ' . app()->settings->get('site_name', APP_NAME),
    'error'     => $error,
    'next'      => $next,
], false);
