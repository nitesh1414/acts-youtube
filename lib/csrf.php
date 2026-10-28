<?php
/**
 * CSRF protection.
 */

/**
 * Current CSRF token for the session (created on first use).
 */
function csrf_token()
{
    if (!session_is_active()) {
        return '';
    }
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = random_token(32);
    }
    return $_SESSION['_csrf'];
}

/**
 * Hidden input for classic HTML forms.
 */
function csrf_field()
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate a token using a timing-safe comparison.
 *
 * @param  mixed $token
 * @return bool
 */
function csrf_valid($token)
{
    $expected = csrf_token();
    return $expected !== '' && is_string($token) && $token !== '' && hash_equals($expected, $token);
}

/**
 * Read the token from a form post, the X-CSRF-Token header or a JSON payload.
 */
function csrf_token_from_request()
{
    if (isset($_POST['_token']) && is_string($_POST['_token'])) {
        return $_POST['_token'];
    }
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    $body = json_input();
    return isset($body['_token']) && is_string($body['_token']) ? $body['_token'] : '';
}

/**
 * Abort with 419 when the CSRF token is missing or wrong.
 */
function csrf_verify()
{
    if (!csrf_valid(csrf_token_from_request())) {
        abort(419, 'Your session expired or the form was tampered with. Please reload the page and try again.');
    }
}
