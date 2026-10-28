<?php
/**
 * PayAccess — application configuration
 * -----------------------------------------------------------------------------
 * You normally do NOT need to edit this file. The installer creates
 * `config.local.php` next to it, which holds your database credentials and
 * site URL. The values below are safe defaults used when no local config
 * exists (handy for development with XAMPP / MAMP defaults).
 *
 * If you prefer editing by hand: copy `config.example.php` to
 * `config.local.php`, fill in your details, and you are done.
 * -----------------------------------------------------------------------------
 */

// 1. Local overrides (created by install.php or copied manually).
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// 2. Defaults (only applied when the local config did not define them).
defined('DB_HOST')    || define('DB_HOST', 'localhost');
defined('DB_PORT')    || define('DB_PORT', '3306');
defined('DB_NAME')    || define('DB_NAME', 'pay_access_db');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');

// Absolute site URL including trailing slash, e.g. https://example.com/
// Leave empty to auto-detect (recommended — works on localhost and sub-folders).
defined('SITE_URL')   || define('SITE_URL', '');

// Set to true only while debugging on a local machine.
defined('APP_DEBUG')  || define('APP_DEBUG', false);

// Application timezone (used for all dates, receipts and reports).
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Kolkata');

// 3. Fixed application constants.
//    Guarded with defined() so the app also boots when another entry point
//    (install.php, update.php) or config.local.php got there first — a plain
//    define() would emit "Constant already defined" warnings.
defined('APP_NAME')       || define('APP_NAME', 'PayAccess');
defined('APP_VERSION')    || define('APP_VERSION', '2.0.0');
defined('APP_ROOT')       || define('APP_ROOT', __DIR__);
defined('APP_START')      || define('APP_START', microtime(true));

// Upload limits for the admin logo uploader.
defined('LOGO_MAX_BYTES') || define('LOGO_MAX_BYTES', 2 * 1024 * 1024); // 2 MB

// Cookie used to remember a verified payment on the buyer's device.
defined('ACCESS_COOKIE')  || define('ACCESS_COOKIE', 'pa_access');

// 4. Boot the application.
require_once APP_ROOT . '/lib/bootstrap.php';
