<?php
/**
 * Application bootstrap: libraries, error handling, session, security headers
 * and the database connection.
 *
 * Included by config.php — every entry point (site, admin, API) starts here.
 */

// -----------------------------------------------------------------------------
// 1. Core libraries
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/lib/helpers.php';
require_once APP_ROOT . '/lib/db.php';
require_once APP_ROOT . '/lib/csrf.php';
require_once APP_ROOT . '/lib/throttle.php';
require_once APP_ROOT . '/lib/auth.php';
require_once APP_ROOT . '/lib/uploads.php';
require_once APP_ROOT . '/lib/youtube.php';
require_once APP_ROOT . '/lib/settings.php';
require_once APP_ROOT . '/lib/razorpay.php';
require_once APP_ROOT . '/lib/payments.php';
require_once APP_ROOT . '/lib/schema.php';
require_once APP_ROOT . '/lib/icons.php';
require_once APP_ROOT . '/lib/view.php';
require_once APP_ROOT . '/lib/access.php';
require_once APP_ROOT . '/lib/app.php';

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Kolkata');
defined('ENABLE_CSP') || define('ENABLE_CSP', true);

// -----------------------------------------------------------------------------
// 2. Error handling
// -----------------------------------------------------------------------------
error_reporting(APP_DEBUG ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_USER_DEPRECATED));
// JSON endpoints never print PHP diagnostics: they are logged, and a warning in
// the body would break the response for the browser. Debug details still reach
// the JSON error message (see the exception handler).
$showErrors = APP_DEBUG && !wants_json();
ini_set('display_errors', $showErrors ? '1' : '0');
ini_set('log_errors', '1');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if (in_array($severity, [E_WARNING, E_USER_WARNING, E_ERROR], true)) {
        app_log('warning', $message, ['file' => $file, 'line' => $line]);
    }
    return false;
});

set_exception_handler(function ($e) {
    app_log('error', get_class($e) . ': ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => substr($e->getTraceAsString(), 0, 2000),
    ]);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $message = 'An unexpected error occurred. Please try again in a moment.';
    if (APP_DEBUG) {
        $message = get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    } elseif ($e instanceof DatabaseException) {
        $message = database_error_hint($e, $dbReference);
    }

    // API / AJAX callers must always receive JSON, never an HTML error page —
    // otherwise the browser shows "unexpected response" and hides the cause.
    if (function_exists('wants_json') && wants_json()) {
        json_response([
            'error' => $message,
            'code'  => $e instanceof DatabaseException ? 'database_error' : 'server_error',
            'ref'   => isset($dbReference) && $dbReference !== null ? $dbReference : null,
        ], 500);
    }

    if (function_exists('app') && app() !== null && !headers_sent()) {
        abort(500, $message);
    }
    plain_error_page('Something went wrong', $message, 500);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        app_log('critical', $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
    }
});

// -----------------------------------------------------------------------------
// 3. Base path (works in sub-folders and from /admin or /api)
// -----------------------------------------------------------------------------
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
$scriptDir  = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$scriptDir  = preg_replace('#/(admin|api)$#', '', $scriptDir);
if ($scriptDir === '.' || $scriptDir === '/') {
    $scriptDir = '';
}
define('BASE_PATH', $scriptDir);

// -----------------------------------------------------------------------------
// 4. Security headers
// -----------------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');

    if (ENABLE_CSP) {
        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "img-src 'self' data: https://*.razorpay.com https://i.ytimg.com https://*.ytimg.com",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' https://checkout.razorpay.com https://cdn.razorpay.com https://*.razorpay.com",
            "connect-src 'self' https://api.razorpay.com https://lumberjack.razorpay.com https://checkout.razorpay.com",
            "frame-src 'self' https://api.razorpay.com https://checkout.razorpay.com https://*.razorpay.com https://www.youtube.com https://www.youtube-nocookie.com",
            "worker-src 'self'",
        ]);
        header('Content-Security-Policy: ' . $csp);
    }
}

// -----------------------------------------------------------------------------
// 5. Session (hardened cookie, idle handling lives in Auth)
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @session_name('pa_session');
    @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();

    // Some shared hosts ship an unwritable session folder: retry in the temp dir.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $sessionDir = rtrim(sys_get_temp_dir(), '/\\') . '/payaccess-sessions';
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0700, true);
        }
        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            @session_save_path($sessionDir);
            @session_start();
        }
    }

    // Last resort: keep request-scoped storage so CSRF tokens and flash messages
    // still work. Admin sign-in requires real sessions (see Auth::attempt()).
    if (session_status() !== PHP_SESSION_ACTIVE) {
        define('SESSION_IN_MEMORY', true);
        $_SESSION = [];
        app_log('warning', 'PHP sessions are unavailable; using request-scoped storage.');
    }
}

// -----------------------------------------------------------------------------
// 6. Database connection
// -----------------------------------------------------------------------------
try {
    $GLOBALS['db'] = Database::fromConfig();
} catch (DatabaseException $e) {
    app_log('critical', 'Database connection failed: ' . $e->getMessage());

    $installer = APP_ROOT . '/install.php';
    if (is_file($installer) && !defined('PAYACCESS_INSTALL_SKIP_BOOTSTRAP')) {
        startup_error_page(
            'Setup required',
            '<p>The application is not connected to a database yet.</p>'
            . '<p>Open <a href="' . e(url('install.php')) . '">install.php</a> to run the 2-minute installer, '
            . 'or edit <code>config.local.php</code> with your MySQL credentials.</p>',
            503
        );
    }

    $details = APP_DEBUG ? '<p><code>' . e($e->getMessage()) . '</code></p>' : '';
    startup_error_page(
        'Database unavailable',
        '<p>The database could not be reached. Please check <code>config.local.php</code> and make sure MySQL is running.</p>' . $details,
        503
    );
}

define('PAYACCESS_INSTALLED', true);

app(new App($GLOBALS['db']));

// Verify that every table and column the app needs actually exists. A v1
// database (or a half-finished install) would otherwise fail later — for
// example while creating a payment — with a confusing 500 error.
$schemaReady = false;
try {
    // update.php / install.php must be able to run while the schema is behind.
    $schemaReady = defined('PAYACCESS_SKIP_SCHEMA_CHECK') || Schema::isCurrent($GLOBALS['db']);
} catch (Throwable $e) {
    app_log('critical', 'Schema check failed: ' . $e->getMessage());
    $schemaReady = false;
}

if (!$schemaReady) {
    $missing = [];
    try {
        $missing = Schema::missing($GLOBALS['db']);
    } catch (Throwable $e) {
        $missing = [];
    }

    $details = '';
    if ($missing !== []) {
        $shown = array_slice($missing, 0, 4);
        $rest = count($missing) - count($shown);
        $details = '<p>Missing: <code>' . e(implode(', ', $shown))
            . ($rest > 0 ? ' and ' . $rest . ' more' : '') . '</code></p>';
    }

    $updater = APP_ROOT . '/update.php';
    $installer = APP_ROOT . '/install.php';

    if (is_file($updater)) {
        $action = '<p><a class="btn" href="' . e(url('update.php')) . '">Update the database</a></p>'
            . '<p><small>Existing payments and settings are kept — the update only adds what is missing.</small></p>';
    } elseif (is_file($installer)) {
        $action = '<p><a class="btn" href="' . e(url('install.php')) . '">Run install.php</a></p>';
    } else {
        $action = '<p>Restore <code>update.php</code> from the package and open it in your browser.</p>';
    }

    // API callers (checkout, verification, webhook) must always get JSON — an
    // HTML page is what used to surface as "unexpected response" in the browser.
    if (wants_json()) {
        json_response([
            'error'   => 'The site database needs an update before payments can work. '
                . 'Open update.php once, then try again.',
            'code'    => 'database_error',
            'details' => array_values($missing), // names the tables/columns to fix
        ], 503);
    }

    startup_error_page(
        'Database update required',
        '<p>The database is missing tables or columns this version needs.</p>' . $details . $action,
        503
    );
}

/**
 * Simple styled page for boot-time failures (before views are usable).
 */
function startup_error_page($title, $html, $status = 500)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code((int) $status);
        header('Content-Type: text/html; charset=utf-8');
    }
    $safeTitle = e($title);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeTitle . '</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;'
        . 'background:radial-gradient(circle at 20% 20%,#1e293b,#0b1120 60%);color:#e2e8f0;padding:24px}'
        . '.box{max-width:560px;background:rgba(15,23,42,.85);border:1px solid #1e2f4d;border-radius:18px;padding:36px;'
        . 'box-shadow:0 24px 60px rgba(0,0,0,.45);text-align:center}h1{font-size:1.4rem;margin:0 0 14px}'
        . 'p{margin:0 0 10px;color:#a9b8ce;line-height:1.65}code{background:#0b1526;padding:2px 6px;border-radius:6px;color:#c7d2fe;font-size:.9em}'
        . 'a{color:#93c5fd}.btn{display:inline-block;margin:8px 0 12px;padding:12px 22px;border-radius:12px;font-weight:700;'
        . 'text-decoration:none;color:#fff;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 12px 26px rgba(79,70,229,.35)}'
        . 'small{color:#7f93b3}</style></head><body><div class="box"><h1>' . $safeTitle . '</h1>' . $html . '</div></body></html>';
    exit;
}
