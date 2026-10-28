<?php
/**
 * Shared helper functions used by the public site, the admin panel and the API.
 */

/**
 * HTML-escape any value for safe output.
 *
 * @param  mixed $value
 * @return string
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * The base path the application is served from ('' for a domain root).
 */
function base_path()
{
    if (defined('BASE_PATH')) {
        return BASE_PATH;
    }
    return '';
}

/**
 * Scheme + host of the current request, e.g. "https://example.com".
 */
function request_origin()
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return 'http://localhost';
    }
    $https  = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
}

/**
 * Absolute URL to a path inside the application.
 *
 * @param  string $path e.g. "admin/login.php" or "" for the home page
 * @return string
 */
function url($path = '')
{
    $base = (defined('SITE_URL') && SITE_URL !== '') ? rtrim(SITE_URL, '/') : request_origin() . base_path();
    $path = ltrim((string) $path, '/');
    return $path === '' ? $base . '/' : $base . '/' . $path;
}

/**
 * URL of a static asset, with a cache-busting version string.
 */
function asset($path)
{
    $path = ltrim((string) $path, '/');
    $file = APP_ROOT . '/' . $path;
    $url  = url($path);
    if (is_file($file)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . filemtime($file);
    }
    return $url;
}

/**
 * URL of an uploaded/public media file stored relative to the app root.
 */
function media_url($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return url($path);
}

/**
 * Redirect (absolute or app-relative) and stop.
 */
function redirect($path, $status = 302)
{
    $target = preg_match('#^https?://#i', (string) $path) ? $path : url($path);
    if (!headers_sent()) {
        header('Location: ' . $target, true, $status);
    }
    echo '<meta http-equiv="refresh" content="0;url=' . e($target) . '">';
    exit;
}

/**
 * Is the current request a POST?
 */
function is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
}

/**
 * Fetch a trimmed string from $_POST.
 */
function post_str($key, $default = '')
{
    return isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

/**
 * Fetch a trimmed string from $_GET.
 */
function query_str($key, $default = '')
{
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

/**
 * Fetch an integer from $_GET.
 */
function query_int($key, $default = 0)
{
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? (int) $_GET[$key] : $default;
}

/**
 * Send a JSON response and stop.
 *
 * @param mixed $data
 */
function json_response($data, $status = 200)
{
    // Anything printed before this point (PHP warning, stray echo) is dropped:
    // a single character in front of the body makes `JSON.parse` fail and turns
    // into "The server returned an unexpected response" in the browser.
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Raw request body (used by JSON endpoints and the Razorpay webhook).
 */
function raw_input()
{
    $raw = file_get_contents('php://input');
    return is_string($raw) ? $raw : '';
}

/**
 * Decode a JSON request body.
 *
 * @return array
 */
function json_input()
{
    $raw = raw_input();
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Is session storage usable for this request?
 *
 * PHP sessions normally are; on misconfigured hosts bootstrap falls back to an
 * in-memory array so CSRF tokens and flash messages still work within the
 * request. Admin logins always require real (persistent) sessions.
 */
function session_is_active()
{
    return session_status() === PHP_SESSION_ACTIVE || (defined('SESSION_IN_MEMORY') && SESSION_IN_MEMORY === true);
}

/**
 * Add a one-time flash message to the session.
 */
function flash($type, $message)
{
    if (!session_is_active()) {
        return;
    }
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Pull and clear all flash messages.
 *
 * @return array<int, array{type:string, message:string}>
 */
function flashes()
{
    if (!session_is_active() || empty($_SESSION['_flash'])) {
        return [];
    }
    $items = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return is_array($items) ? $items : [];
}

/**
 * Remember form input after a validation error.
 */
function flash_input(array $input)
{
    if (session_is_active()) {
        unset($input['password'], $input['current_password'], $input['new_password'], $input['confirm_password']);
        $_SESSION['_old'] = $input;
    }
}

/**
 * Old form input, or a default value.
 *
 * @param  string $key
 * @param  mixed  $default
 * @return mixed
 */
function old($key, $default = '')
{
    if (session_is_active() && isset($_SESSION['_old']) && is_array($_SESSION['_old'])) {
        return array_key_exists($key, $_SESSION['_old']) ? $_SESSION['_old'][$key] : $default;
    }
    return $default;
}

/**
 * Forget remembered form input (called after a successful submit).
 */
function clear_old_input()
{
    unset($_SESSION['_old']);
}

/**
 * Currency symbol for a currency code.
 */
function currency_symbol($currency = 'INR')
{
    $map = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'AED '];
    $code = strtoupper((string) $currency);
    return isset($map[$code]) ? $map[$code] : $code . ' ';
}

/**
 * Format an amount, e.g. money(199) => "₹199", money(199.5) => "₹199.50".
 *
 * @param  float|string $amount
 */
function money($amount, $currency = 'INR')
{
    $value = (float) $amount;
    $decimals = (abs($value - round($value)) < 0.005) ? 0 : 2;
    return currency_symbol($currency) . number_format($value, $decimals);
}

/**
 * Format an amount for a receipt, always with two decimals.
 *
 * @param  float|string $amount
 */
function money_exact($amount, $currency = 'INR')
{
    return currency_symbol($currency) . number_format((float) $amount, 2);
}

/**
 * Human friendly date, e.g. "12 Mar 2025, 4:05 PM".
 */
function date_display($datetime, $fallback = '—')
{
    if (empty($datetime)) {
        return $fallback;
    }
    $ts = strtotime((string) $datetime);
    return $ts ? date('d M Y, g:i A', $ts) : $fallback;
}

/**
 * Human friendly short date, e.g. "12 Mar 2025".
 */
function date_short($datetime, $fallback = '—')
{
    if (empty($datetime)) {
        return $fallback;
    }
    $ts = strtotime((string) $datetime);
    return $ts ? date('d M Y', $ts) : $fallback;
}

/**
 * Current timestamp in MySQL DATETIME format.
 */
function now()
{
    return date('Y-m-d H:i:s');
}

/**
 * Initials for an avatar bubble, e.g. "Nitesh Kumar" => "NK".
 */
function initials($name)
{
    $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $name))));
    $first = isset($parts[0]) ? $parts[0] : '';
    $last  = count($parts) > 1 ? (string) end($parts) : '';
    return strtoupper(text_sub($first, 0, 1) . text_sub($last, 0, 1));
}

/**
 * Multibyte-safe string length (falls back to strlen).
 */
function text_length($value)
{
    return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value);
}

/**
 * Multibyte-safe substring (falls back to substr).
 */
function text_sub($value, $start, $length = null)
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr((string) $value, $start, null, 'UTF-8') : mb_substr((string) $value, $start, $length, 'UTF-8');
    }
    return $length === null ? substr((string) $value, $start) : substr((string) $value, $start, $length);
}

/**
 * Shorten a string with an ellipsis.
 */
function text_limit($value, $limit = 60)
{
    $value = trim((string) $value);
    if (text_length($value) <= $limit) {
        return $value;
    }
    return rtrim(text_sub($value, 0, $limit - 1)) . '…';
}

/**
 * URL-safe random token.
 */
function random_token($bytes = 32)
{
    try {
        return bin2hex(random_bytes($bytes));
    } catch (Exception $e) {
        return substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, $bytes * 2);
    }
}

/**
 * Client IP address (proxy aware, validated).
 */
function client_ip()
{
    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
            $candidates[] = trim($part);
        }
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = $_SERVER['REMOTE_ADDR'];
    }
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '0.0.0.0';
}

/**
 * Is this request served over HTTPS?
 */
function is_https()
{
    return strpos(request_origin(), 'https://') === 0;
}

/**
 * Validate an e-mail address.
 */
function valid_email($email)
{
    return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && text_length($email) <= 190;
}

/**
 * Reduce a phone number to digits only (keeps a leading + conversion simple).
 */
function normalize_mobile($mobile)
{
    $digits = preg_replace('/\D+/', '', (string) $mobile);
    // Drop a leading country code 91 for 12-digit Indian numbers, keep last 10.
    if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 11 && strpos($digits, '0') === 0) {
        $digits = substr($digits, 1);
    }
    return (string) $digits;
}

/**
 * Validate a mobile number (7-15 digits after normalisation).
 */
function valid_mobile($mobile)
{
    $digits = normalize_mobile($mobile);
    return strlen($digits) >= 7 && strlen($digits) <= 15;
}

/**
 * Format a mobile number for display, e.g. "+91 98765 43210" for Indian numbers.
 */
function display_mobile($mobile)
{
    $digits = normalize_mobile($mobile);
    if (strlen($digits) === 10) {
        return '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5);
    }
    return $digits;
}

/**
 * Mask an e-mail address for receipts, e.g. "ni***@example.com".
 */
function mask_email($email)
{
    $email = (string) $email;
    $at = strpos($email, '@');
    if ($at === false || $at < 2) {
        return $email;
    }
    return substr($email, 0, 2) . str_repeat('*', max(1, $at - 2)) . substr($email, $at);
}

/**
 * Human readable file size.
 */
function format_bytes($bytes)
{
    $bytes = (float) $bytes;
    $units = ['B', 'KB', 'MB', 'GB'];
    $index = 0;
    while ($bytes >= 1024 && $index < count($units) - 1) {
        $bytes /= 1024;
        $index++;
    }
    return round($bytes, $index === 0 ? 0 : 1) . ' ' . $units[$index];
}

/**
 * Append query parameters to the current URL (used by sorting/pagination links).
 *
 * @param array<string, mixed> $params
 */
function current_url_with(array $params)
{
    $query = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    return $script . ($query ? '?' . http_build_query($query) : '');
}

/**
 * Write a line to the application log (storage/logs). Never throws.
 */
function app_log($level, $message, array $context = [])
{
    static $logging = false;
    if ($logging) {
        return;
    }
    $logging = true;

    $dir = APP_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = json_encode([
        'time'    => date('c'),
        'level'   => $level,
        'message' => $message,
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $file = $dir . '/app-' . date('Y-m-d') . '.log';
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        // Keep the log folder tidy (roughly 5 MB per file).
        if (@filesize($file) > 5 * 1024 * 1024) {
            @rename($file, $file . '.1');
        }
    } else {
        @error_log('[PayAccess] ' . $level . ': ' . $message);
    }

    $logging = false;
}

/**
 * Turn a database error into something a site owner can act on.
 */
function database_error_hint(Throwable $error)
{
    $message = (string) $error->getMessage();

    if (preg_match('/Unknown column|doesn\'t exist|no such (table|column)|Table .* doesn\'t exist/i', $message)) {
        return 'The database schema is out of date. Open update.php in your browser to add the missing '
            . 'tables and columns (your data is kept).';
    }
    if (preg_match('/Access denied|Connection refused|Unknown database|SQLSTATE\[HY000\]\s*\[104/i', $message)) {
        return 'The database could not be reached. Check the credentials in config.local.php.';
    }
    if (preg_match('/Duplicate entry/i', $message)) {
        return 'A duplicate record was detected. Please try again.';
    }
    if (defined('APP_DEBUG') && APP_DEBUG) {
        return $message;
    }
    return 'A database error occurred. Please try again in a moment.';
}

/**
 * Truncate a value so it fits a VARCHAR column.
 */
function db_limit($value, $length)
{
    return text_sub(trim((string) $value), 0, $length);
}

/**
 * Build a "LIKE" pattern for a search term.
 */
function like_term($term)
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $term)) . '%';
}

/**
 * Is the app reachable/installed? (used by the installer and bootstrap).
 */
function is_installed()
{
    return defined('PAYACCESS_INSTALLED') && PAYACCESS_INSTALLED === true;
}

/**
 * Render a simple message page (used for fatal errors before the DB is up).
 */
function plain_error_page($title, $message, $status = 500)
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }
    $title   = e($title);
    $message = e($message);
    echo <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e2e8f0;padding:24px}
.box{max-width:520px;background:#111c34;border:1px solid #1f2f4d;border-radius:16px;padding:32px;text-align:center}
h1{font-size:1.35rem;margin:0 0 12px}p{margin:0;color:#a8b6cc;line-height:1.6}
code{background:#0b1526;padding:2px 6px;border-radius:6px;color:#c7d2fe}
a{color:#93c5fd}
</style></head><body><div class="box"><h1>{$title}</h1><p>{$message}</p></div></body></html>
HTML;
    exit;
}
