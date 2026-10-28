<?php
/**
 * Regenerate sql/schema.sql from lib/schema.php.
 *
 *   php tools/build-schema.php
 *
 * Run this after changing the table definitions so manual installs and the
 * installer stay in sync. Not used at runtime.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'PayAccess');
define('APP_DEBUG', false);
define('APP_TIMEZONE', 'Asia/Kolkata');

require_once APP_ROOT . '/lib/helpers.php';
require_once APP_ROOT . '/lib/db.php';
require_once APP_ROOT . '/lib/schema.php';

$target = APP_ROOT . '/sql/schema.sql';
$sql = Schema::sqlDump();

if (file_put_contents($target, $sql) === false) {
    fwrite(STDERR, "Could not write {$target}\n");
    exit(1);
}

echo "Wrote {$target} (" . strlen($sql) . " bytes, schema v" . Schema::VERSION . ")\n";
