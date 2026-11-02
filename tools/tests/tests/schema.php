<?php
/**
 * Schema tests: v3 definitions, drift detection, upgrade paths.
 * Executed by run-schema.mjs inside php-wasm.
 */

require '/app/lib/helpers.php';
require '/app/lib/db.php';
require '/app/lib/schema.php';
require '/app/lib/settings.php';
require '/app/tests-support/fakedb.php';

$pass = 0;
$fail = [];
function check($condition, $label)
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        return;
    }
    $fail[] = $label;
}

// ---------------------------------------------------------------------------
// Definition
// ---------------------------------------------------------------------------
$tables = Schema::tables();
check(Schema::VERSION === 3, 'Schema::VERSION is 3, got ' . Schema::VERSION);

foreach (['settings', 'admin_users', 'payments', 'payment_events', 'videos', 'bundles', 'bundle_items', 'payment_videos', 'rate_events'] as $table) {
    check(isset($tables[$table]), 'table definition present: ' . $table);
}
foreach (['product_type', 'product_id', 'product_name'] as $column) {
    check(isset($tables['payments']['columns'][$column]), 'payments.' . $column . ' defined');
}
check(stripos($tables['settings']['columns']['access_days'], 'DEFAULT 0') !== false,
    'settings.access_days defaults to 0 (lifetime)');
check(isset($tables['bundle_items']['indexes']['uniq_bundle_video']), 'bundle_items unique index');
check(isset($tables['payment_videos']['indexes']['uniq_payment_video']), 'payment_videos unique index');
check(stripos($tables['payments']['columns']['access_expires_at'], 'NOT NULL') === false,
    'payments.access_expires_at allows NULL');

// ---------------------------------------------------------------------------
// SQL dump
// ---------------------------------------------------------------------------
$dump = Schema::sqlDump();
foreach (['CREATE TABLE IF NOT EXISTS `videos`', 'CREATE TABLE IF NOT EXISTS `bundles`',
          'CREATE TABLE IF NOT EXISTS `bundle_items`', 'CREATE TABLE IF NOT EXISTS `payment_videos`',
          '`product_name`'] as $needle) {
    check(strpos($dump, $needle) !== false, 'sqlDump contains ' . $needle);
}
$file = @file_get_contents('/app/sql/schema.sql');
check($file !== false, 'sql/schema.sql exists');
if ($file !== false) {
    check(trim($file) === trim($dump), 'sql/schema.sql matches Schema::sqlDump() (run node run-tools.mjs)');
}

// ---------------------------------------------------------------------------
// Drift detection
// ---------------------------------------------------------------------------
Schema::clearMarker();
$db = FakeDatabase::reset();
$db->install(false);
check(Schema::isCurrent($db) === false, 'empty database is not current');
$joined = implode(' | ', Schema::missing($db));
foreach (['videos', 'bundles', 'bundle_items', 'payment_videos', 'settings'] as $table) {
    check(strpos($joined, 'table `' . $table . '`') !== false, 'missing reports table ' . $table);
}

$db = FakeDatabase::reset();
$db->install(true);
Schema::clearMarker();
check(Schema::isCurrent($db) === true, 'v3 database is current');
check(Schema::missing($db) === [], 'nothing missing on a v3 database: ' . json_encode(Schema::missing($db)));

$db->dropTable('payment_videos');
Schema::clearMarker();
check(Schema::isCurrent($db) === false, 'dropping payment_videos makes the schema stale');
check(strpos(implode(' | ', Schema::missing($db)), 'payment_videos') !== false, 'missing names payment_videos');

$db = FakeDatabase::reset();
$db->install(true);
unset($db->schema['payments']['product_name']);
Schema::clearMarker();
check(strpos(implode(' | ', Schema::missing($db)), 'payments.product_name') !== false, 'missing names payments.product_name');

$db = FakeDatabase::reset();
$db->install(true);
$db->schema['payments']['order_id']['Null'] = 'NO';
Schema::clearMarker();
check(stripos(implode(' | ', Schema::missing($db)), 'payments.order_id') !== false, 'v1 NOT NULL order_id is reported');

// ---------------------------------------------------------------------------
// v2 → v3 upgrade
// ---------------------------------------------------------------------------
$db = FakeDatabase::reset();
$db->install(false);
$allTables = Schema::tables();
foreach (['settings', 'admin_users', 'payments', 'payment_events', 'rate_events'] as $table) {
    $db->tables[$table] = [];
    $db->schema[$table] = [];
    foreach ($allTables[$table]['columns'] as $column => $definition) {
        $db->schema[$table][$column] = [
            'Field' => $column, 'Type' => 'varchar(255)',
            'Null' => stripos($definition, 'NOT NULL') !== false ? 'NO' : 'YES',
            'Key' => '', 'Default' => null,
            'Extra' => stripos($definition, 'AUTO_INCREMENT') !== false ? 'auto_increment' : '',
        ];
    }
}
$settings = Settings::DEFAULTS;
$settings['youtube_video_url'] = 'https://www.youtube.com/watch?v=legacy12345';
$settings['access_days'] = '30';
$settings['amount'] = '499.00';
$db->tables['settings'][] = array_merge($settings, ['id' => 1, 'updated_at' => now()]);
$db->tables['payments'][] = [
    'id' => 7, 'order_id' => 'order_old', 'payment_id' => 'pay_old',
    'user_name' => 'Old Buyer', 'user_email' => 'old@example.com', 'user_mobile' => '9000000000',
    'amount' => '499.00', 'currency' => 'INR', 'status' => 'success', 'method' => 'upi',
    'access_token' => str_repeat('ab', 16), 'access_expires_at' => date('Y-m-d H:i:s', strtotime('+20 days')),
    'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    'ip_address' => '127.0.0.1', 'user_agent' => 'v2',
];
$db->schema['payments']['order_id']['Null'] = 'NO';
foreach (['product_type', 'product_id', 'product_name'] as $column) {
    unset($db->schema['payments'][$column]);
}

$messages = [];
$report = Schema::migrate($db, function ($message) use (&$messages) {
    $messages[] = $message;
});

check($report['ok'] === true, 'migration reports ok: ' . json_encode($report['errors']));
foreach (['videos', 'bundles', 'bundle_items', 'payment_videos'] as $table) {
    check(in_array($table, $report['tables_created'], true), 'migration created ' . $table);
}
foreach (['product_type', 'product_id', 'product_name'] as $column) {
    check(in_array('payments.' . $column, $report['columns_added'], true), 'migration added payments.' . $column);
}
check($db->schema['payments']['order_id']['Null'] === 'YES', 'migration relaxed order_id to NULL');

Schema::clearMarker();
check(Schema::isCurrent($db) === true, 'database is current after migration');
check(Schema::missing($db) === [], 'nothing missing after migration');

$videos = $db->tables['videos'];
check(count($videos) === 1, 'migration seeded exactly one video, got ' . count($videos));
if (isset($videos[0])) {
    check($videos[0]['youtube_url'] === 'https://www.youtube.com/watch?v=legacy12345', 'seeded video keeps the legacy link');
    check((float) $videos[0]['price'] === 499.0, 'seeded video inherits the old price');
    check((int) $videos[0]['is_active'] === 1, 'seeded video is on sale');
}
$entitlements = $db->tables['payment_videos'];
check(count($entitlements) === 1, 'migration back-filled one entitlement, got ' . count($entitlements));
if (isset($entitlements[0])) {
    check((int) $entitlements[0]['payment_id'] === 7, 'entitlement points at the old payment');
    check(isset($videos[0]) && (int) $entitlements[0]['video_id'] === (int) $videos[0]['id'], 'entitlement points at the seeded video');
}
check((int) $db->tables['settings'][0]['access_days'] === 0, 'the old 30-day default flipped to lifetime');
check(!empty($messages), 'migration logged progress');

Schema::clearMarker();
$second = Schema::migrate($db);
check($second['tables_created'] === [], 'second migration creates no tables');
check($second['columns_added'] === [], 'second migration adds no columns');
check(count($db->tables['videos']) === 1, 'second migration does not duplicate videos');
check(count($db->tables['payment_videos']) === 1, 'second migration does not duplicate entitlements');

// ---------------------------------------------------------------------------
// Fresh install
// ---------------------------------------------------------------------------
$db = FakeDatabase::reset();
$db->install(false);
Schema::clearMarker();
$fresh = Schema::migrate($db);
check($fresh['ok'] === true, 'fresh migration ok');
check(count($fresh['tables_created']) === count(Schema::tables()), 'fresh migration creates every table');
check((int) $db->value('SELECT COUNT(*) FROM videos', [], -1) === 0, 'fresh install has an empty library');
Schema::clearMarker();
check(Schema::isCurrent($db) === true, 'fresh install is current');

// ---------------------------------------------------------------------------
echo $pass . " passed, " . count($fail) . " failed\n";
foreach ($fail as $message) {
    echo "FAIL " . $message . "\n";
}
