<?php
/**
 * Copy this file to `config.local.php` and adjust the values.
 * (install.php does this for you automatically.)
 */

// ---- Database ---------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'pay_access_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Site -------------------------------------------------------------------
// Absolute URL with trailing slash, e.g. 'https://pay.example.com/'.
// Leave empty ('') to auto-detect the URL — safest on shared hosting.
define('SITE_URL', '');

// Show detailed error messages (never enable this in production).
defined('APP_DEBUG') || define('APP_DEBUG', false);

// Timezone used for reports and receipts.
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Kolkata');

// ---- Optional tuning --------------------------------------------------------
// Maximum size of an uploaded logo, in bytes (default 2 MB).
// define('LOGO_MAX_BYTES', 2 * 1024 * 1024);
