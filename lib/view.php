<?php
/**
 * Very small view layer: templates live in /templates/{site,admin} and are
 * wrapped by the layout of their area.
 */

/**
 * Render a template with layout and exit-free output.
 *
 * @param string               $view e.g. "site/home" or "admin/dashboard"
 * @param array<string, mixed> $data
 */
function render($view, array $data = [], $layout = null)
{
    echo render_to_string($view, $data, $layout);
}

/**
 * Render a template and return the HTML.
 *
 * @param  string               $view
 * @param  array<string, mixed> $data
 * @param  string|false|null    $layout Layout name inside the area, false for none
 * @return string
 */
function render_to_string($view, array $data = [], $layout = null)
{
    $view = trim((string) $view, '/');
    $segments = explode('/', $view);
    $area = count($segments) > 1 ? array_shift($segments) : 'site';
    if (!in_array($area, ['site', 'admin'], true)) {
        throw new RuntimeException('Unknown view area: ' . $area);
    }
    $template = APP_ROOT . '/templates/' . $area . '/' . implode('/', $segments) . '.php';
    if (!is_file($template)) {
        throw new RuntimeException('View not found: ' . $view);
    }

    $app = app();
    $defaults = [
        'settings'   => $app ? $app->settings->all() : Settings::DEFAULTS,
        'flashes'    => flashes(),
        'csrf'       => csrf_token(),
        'pageTitle'  => null,
        'metaDescription' => '',
        'bodyClass'  => '',
        'currentPath' => isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '',
    ];
    $data = array_merge($defaults, $data);

    if ($area === 'admin' && !array_key_exists('admin', $data)) {
        $data['admin'] = $app ? $app->auth->user() : null;
    }

    if ($data['pageTitle'] === null) {
        $data['pageTitle'] = $area === 'admin'
            ? 'Admin · ' . $data['settings']['site_name']
            : $data['settings']['site_name'];
    }

    $content = capture_template($template, $data);
    if ($layout === false) {
        return $content;
    }
    $layoutFile = APP_ROOT . '/templates/' . $area . '/' . ($layout !== null ? $layout : 'layout') . '.php';
    if (!is_file($layoutFile)) {
        return $content;
    }
    return capture_template($layoutFile, array_merge($data, ['content' => $content]));
}

/**
 * Include a template with extracted variables and return its output.
 *
 * @param  string               $__template
 * @param  array<string, mixed> $__data
 * @return string
 */
function capture_template($__template, array $__data)
{
    extract($__data, EXTR_SKIP);
    ob_start();
    try {
        require $__template;
    } catch (Throwable $__e) {
        ob_end_clean();
        throw $__e;
    }
    return (string) ob_get_clean();
}

/**
 * Render an error page and stop.
 *
 * @param int    $status
 * @param string $message
 */
function abort($status = 404, $message = 'The page you are looking for could not be found.', $title = '')
{
    if (!headers_sent()) {
        http_response_code((int) $status);
    }
    $titles = [
        400 => 'Bad request',
        403 => 'Access denied',
        404 => 'Page not found',
        405 => 'Method not allowed',
        419 => 'Session expired',
        429 => 'Too many requests',
        500 => 'Something went wrong',
        503 => 'Service unavailable',
    ];
    $heading = $title !== '' ? $title : (isset($titles[$status]) ? $titles[$status] : 'Error');

    try {
        echo render_to_string('site/error', [
            'pageTitle'  => $heading,
            'statusCode' => (int) $status,
            'heading'    => $heading,
            'message'    => $message,
        ]);
    } catch (Throwable $e) {
        plain_error_page($heading, $message, (int) $status);
    }
    exit;
}

/**
 * Abort unless the request method matches.
 */
function require_method($method)
{
    $current = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    if ($current !== strtoupper((string) $method)) {
        if (wants_json()) {
            json_response(['error' => 'Method not allowed.'], 405);
        }
        abort(405, 'This action only accepts ' . strtoupper((string) $method) . ' requests.');
    }
}

/**
 * Does the client expect JSON?
 */
function wants_json()
{
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        return true;
    }
    $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    return strpos($script, '/api/') !== false;
}
