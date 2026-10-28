<?php
/**
 * PayAccess installer.
 *
 * Creates config.local.php, the database schema and the first admin account.
 * Delete this file (and install.lock when you want to reinstall) afterwards.
 */

define('APP_ROOT', __DIR__);
define('APP_NAME', 'PayAccess');
define('APP_VERSION', '2.0.0');
define('APP_DEBUG', false);
define('APP_TIMEZONE', 'Asia/Kolkata');

require_once APP_ROOT . '/lib/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$configFile = APP_ROOT . '/config.local.php';
$lockFile   = APP_ROOT . '/install.lock';
$schemaFile = APP_ROOT . '/sql/schema.sql';

$locked = is_file($lockFile);

$defaults = [
    'db_host'    => 'localhost',
    'db_port'    => '3306',
    'db_name'    => 'pay_access_db',
    'db_user'    => 'root',
    'db_pass'    => '',
    'site_url'   => '',
    'site_name'  => 'PayAccess',
    'amount'     => '199',
    'access_days'=> '30',
    'demo_mode'  => '0',
    'username'   => 'admin',
    'admin_name' => 'Administrator',
    'admin_email'=> '',
];

$values = $defaults;
$errors = [];
$installed = false;
$configWritten = false;
$configContent = '';
$seedDemo = false;

/* -------------------------------------------------------------------------
 * Requirement checks
 * ---------------------------------------------------------------------- */
$uploadDir = APP_ROOT . '/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$checks = [
    [
        'label'    => 'PHP 7.4 or newer',
        'ok'       => version_compare(PHP_VERSION, '7.4.0', '>='),
        'detail'   => 'Running PHP ' . PHP_VERSION,
        'required' => true,
    ],
    [
        'label'    => 'PDO MySQL driver',
        'ok'       => extension_loaded('pdo_mysql'),
        'detail'   => extension_loaded('pdo_mysql') ? 'pdo_mysql enabled' : 'Enable the pdo_mysql extension in php.ini',
        'required' => true,
    ],
    [
        'label'    => 'JSON + Session support',
        'ok'       => function_exists('json_encode') && function_exists('session_start'),
        'detail'   => 'Built into PHP',
        'required' => true,
    ],
    [
        'label'    => 'HTTPS client for Razorpay (cURL or allow_url_fopen)',
        'ok'       => function_exists('curl_init') || (bool) ini_get('allow_url_fopen'),
        'detail'   => function_exists('curl_init') ? 'cURL available' : 'Falling back to PHP streams',
        'required' => true,
    ],
    [
        'label'    => 'Image validation (fileinfo or getimagesize)',
        'ok'       => class_exists('finfo') || function_exists('getimagesize'),
        'detail'   => class_exists('finfo') ? 'fileinfo enabled' : 'getimagesize fallback',
        'required' => true,
    ],
    [
        'label'    => 'Multi-byte strings (mbstring)',
        'ok'       => function_exists('mb_strlen'),
        'detail'   => function_exists('mb_strlen') ? 'mbstring enabled' : 'Optional — byte functions will be used',
        'required' => false,
    ],
    [
        'label'    => 'uploads/ folder writable',
        'ok'       => is_dir($uploadDir) && is_writable($uploadDir),
        'detail'   => is_writable($uploadDir) ? 'Writable' : 'chmod 755 (or 775) the uploads folder',
        'required' => true,
    ],
    [
        'label'    => 'Root folder writable (config.local.php)',
        'ok'       => is_writable(APP_ROOT),
        'detail'   => is_writable(APP_ROOT) ? 'Writable' : 'Not writable — you will get the config to paste manually',
        'required' => false,
    ],
    [
        'label'    => 'Schema file present',
        'ok'       => is_file($schemaFile),
        'detail'   => is_file($schemaFile) ? 'sql/schema.sql found' : 'sql/schema.sql is missing',
        'required' => true,
    ],
];

$requirementsOk = true;
foreach ($checks as $check) {
    if ($check['required'] && !$check['ok']) {
        $requirementsOk = false;
    }
}

/* -------------------------------------------------------------------------
 * Handle the install request
 * ---------------------------------------------------------------------- */
if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($defaults) as $key) {
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
            $values[$key] = trim((string) $_POST[$key]);
        }
    }
    $seedDemo = !empty($_POST['seed_demo']);

    $csrf = isset($_POST['_token']) ? (string) $_POST['_token'] : '';
    if (!empty($_SESSION['install_csrf']) && !hash_equals($_SESSION['install_csrf'], $csrf)) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }

    if (!preg_match('/^[A-Za-z0-9_.-]{1,253}$/', $values['db_host'])) {
        $errors[] = 'Enter a valid database host.';
    }
    if (!ctype_digit($values['db_port']) || (int) $values['db_port'] < 1 || (int) $values['db_port'] > 65535) {
        $errors[] = 'Enter a valid database port (usually 3306).';
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $values['db_name'])) {
        $errors[] = 'The database name may only contain letters, numbers and underscores.';
    }
    if ($values['db_user'] === '') {
        $errors[] = 'Enter the database username.';
    }
    if ($values['site_name'] === '' || text_length($values['site_name']) > 120) {
        $errors[] = 'Enter a site name (maximum 120 characters).';
    }
    if (!is_numeric($values['amount']) || (float) $values['amount'] < 1) {
        $errors[] = 'Enter a valid price.';
    }
    if (!ctype_digit($values['access_days']) || (int) $values['access_days'] < 1 || (int) $values['access_days'] > 3650) {
        $errors[] = 'Access days must be a number between 1 and 3650.';
    }
    if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,31}$/', strtolower($values['username']))) {
        $errors[] = 'The admin username must be 3-32 characters (letters, numbers, dot, dash, underscore).';
    }
    if ($values['admin_name'] === '') {
        $errors[] = 'Enter the admin full name.';
    }
    if (!valid_email($values['admin_email'])) {
        $errors[] = 'Enter a valid admin e-mail address.';
    }
    $adminPassword = isset($_POST['admin_password']) ? (string) $_POST['admin_password'] : '';
    $adminConfirm  = isset($_POST['admin_password_confirm']) ? (string) $_POST['admin_password_confirm'] : '';
    if (strlen($adminPassword) < 8) {
        $errors[] = 'The admin password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $adminPassword) || !preg_match('/[0-9]/', $adminPassword)) {
        $errors[] = 'The admin password must contain at least one letter and one number.';
    } elseif ($adminPassword !== $adminConfirm) {
        $errors[] = 'The two admin passwords do not match.';
    }

    if (!$requirementsOk) {
        $errors[] = 'Please fix the missing requirements before installing.';
    }
    if ($values['site_url'] !== '' && !filter_var($values['site_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'The site URL is not valid (leave it empty to auto-detect).';
    }

    if (empty($errors)) {
        try {
            // --- Database connection & schema --------------------------------
            $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $values['db_host'], $values['db_port']);
            $pdo = new PDO($serverDsn, $values['db_user'], $values['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 8,
            ]);
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                $values['db_name']
            ));
            $pdo->exec(sprintf('USE `%s`', $values['db_name']));

            $schema = (string) file_get_contents($schemaFile);
            $schema = preg_replace('/^\s*--.*$/m', '', $schema);
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }

            // --- Admin account ----------------------------------------------
            $existingAdmin = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
            $existingAdmin->execute([strtolower($values['username'])]);
            if ($existingAdmin->fetchColumn()) {
                throw new RuntimeException('An admin with that username already exists. Choose another username.');
            }

            $insertAdmin = $pdo->prepare(
                'INSERT INTO admin_users (username, name, email, password_hash, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, NOW(), NOW())'
            );
            $insertAdmin->execute([
                strtolower($values['username']),
                $values['admin_name'],
                strtolower($values['admin_email']),
                password_hash($adminPassword, PASSWORD_DEFAULT),
            ]);

            // --- Settings ---------------------------------------------------
            $updateSettings = $pdo->prepare(
                'UPDATE settings SET site_name = ?, logo_text = ?, amount = ?, access_days = ?, demo_mode = ?, updated_at = NOW() WHERE id = 1'
            );
            $updateSettings->execute([
                $values['site_name'],
                $values['site_name'],
                number_format((float) $values['amount'], 2, '.', ''),
                (int) $values['access_days'],
                $values['demo_mode'] === '1' ? 1 : 0,
            ]);

            // --- Optional demo payments -------------------------------------
            if ($seedDemo) {
                $demoRows = [
                    ['Aarav Sharma', 'aarav.sharma@example.com', '9876543210', 'success', 6, 'pay_DemoA1B2C3D4'],
                    ['Priya Menon', 'priya.menon@example.com', '9822011223', 'success', 3, 'pay_DemoE5F6G7H8'],
                    ['Rohit Verma', 'rohit.verma@example.com', '9812345678', 'pending', 1, null],
                    ['Sneha Patil', 'sneha.patil@example.com', '9765432109', 'failed', 2, null],
                ];
                $insertPayment = $pdo->prepare(
                    "INSERT INTO payments
                        (user_name, user_email, user_mobile, amount, currency, status, order_id, payment_id, method,
                         access_token, access_expires_at, ip_address, created_at, paid_at, updated_at)
                     VALUES (?, ?, ?, ?, 'INR', ?, ?, ?, ?, ?, ?, '127.0.0.1', ?, ?, ?)"
                );
                foreach ($demoRows as $index => $row) {
                    list($name, $email, $mobile, $status, $daysAgo, $razorpayId) = $row;
                    $created = date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days'));
                    $paid = $status === 'success' ? date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days +2 minutes')) : null;
                    $token = $status === 'success' ? random_token(32) : null;
                    $expires = $status === 'success' ? date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days +' . (int) $values['access_days'] . ' days')) : null;
                    $orderId = 'order_demo_' . ($index + 1) . bin2hex(random_bytes(4));

                    $insertPayment->execute([
                        $name,
                        $email,
                        $mobile,
                        number_format((float) $values['amount'], 2, '.', ''),
                        $status,
                        $orderId,
                        $razorpayId,
                        $status === 'success' ? 'upi' : null,
                        $token,
                        $expires,
                        $created,
                        $paid,
                        $created,
                    ]);
                }
            }

            // --- config.local.php -------------------------------------------
            $configContent = "<?php\n"
                . "/**\n"
                . " * PayAccess configuration — generated by install.php on " . date('d M Y, H:i') . ".\n"
                . " * Keep this file out of version control.\n"
                . " */\n\n"
                . "define('DB_HOST', " . var_export($values['db_host'], true) . ");\n"
                . "define('DB_PORT', " . var_export($values['db_port'], true) . ");\n"
                . "define('DB_NAME', " . var_export($values['db_name'], true) . ");\n"
                . "define('DB_USER', " . var_export($values['db_user'], true) . ");\n"
                . "define('DB_PASS', " . var_export($values['db_pass'], true) . ");\n\n"
                . "define('SITE_URL', " . var_export(rtrim($values['site_url'], '/') . ($values['site_url'] !== '' ? '/' : ''), true) . ");\n"
                . "define('APP_DEBUG', false);\n"
                . "define('APP_TIMEZONE', 'Asia/Kolkata');\n";

            if (is_writable(APP_ROOT) || (is_file($configFile) && is_writable($configFile))) {
                if (@file_put_contents($configFile, $configContent) !== false) {
                    @chmod($configFile, 0640);
                    $configWritten = true;
                }
            }

            @file_put_contents($lockFile, 'Installed ' . date('c') . PHP_EOL);

            $installed = true;
            unset($_SESSION['install_csrf']);
            unset($adminPassword, $adminConfirm);
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['install_csrf'];

/* -------------------------------------------------------------------------
 * View helpers
 * ---------------------------------------------------------------------- */
function installer_head($title)
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . e($title) . '</title><style>'
        . ':root{color-scheme:dark}*{box-sizing:border-box}'
        . 'body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:'
        . 'radial-gradient(45% 45% at 20% 10%,#1e293b,#070b14 65%);color:#e2e8f0;min-height:100vh;padding:34px 16px;line-height:1.6}'
        . '.wrap{max-width:960px;margin:0 auto}'
        . '.brand{display:flex;align-items:center;gap:12px;margin-bottom:22px;font-weight:700;font-size:1.1rem}'
        . '.brand span{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,#6366f1,#8b5cf6)}'
        . '.card{background:rgba(17,28,51,.78);border:1px solid rgba(148,163,184,.18);border-radius:20px;padding:28px;box-shadow:0 30px 70px rgba(2,6,18,.5)}'
        . 'h1{font-size:1.6rem;margin:0 0 6px;letter-spacing:-.02em}h2{font-size:1.05rem;margin:26px 0 12px}'
        . 'p{margin:0 0 12px;color:#a9b8ce}a{color:#a5b4fc}'
        . '.grid{display:grid;grid-template-columns:1fr 1fr;gap:26px}'
        . '@media(max-width:820px){.grid{grid-template-columns:1fr}}'
        . 'ul.checks{list-style:none;padding:0;margin:0;display:grid;gap:8px}'
        . 'ul.checks li{display:flex;gap:10px;align-items:flex-start;font-size:.9rem;padding:9px 12px;border-radius:10px;'
        . 'border:1px solid rgba(148,163,184,.16);background:rgba(148,163,184,.05)}'
        . 'ul.checks li .dot{width:18px;height:18px;border-radius:50%;flex:none;display:grid;place-items:center;font-size:.7rem;font-weight:700}'
        . '.ok .dot{background:rgba(16,185,129,.2);color:#34d399}.bad .dot{background:rgba(239,68,68,.2);color:#f87171}'
        . '.opt .dot{background:rgba(245,158,11,.2);color:#fbbf24}'
        . 'label{display:block;font-size:.82rem;font-weight:600;margin:0 0 5px;color:#dbe4f2}'
        . 'input{width:100%;padding:10px 13px;border-radius:10px;border:1px solid rgba(148,163,184,.24);'
        . 'background:rgba(4,9,20,.6);color:#e8eefb;font:inherit;font-size:.92rem}'
        . 'input:focus{outline:none;border-color:#818cf8;box-shadow:0 0 0 3px rgba(99,102,241,.2)}'
        . '.field{margin-bottom:14px}.row{display:grid;grid-template-columns:2fr 1fr;gap:12px}'
        . '.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}'
        . '.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 22px;border:0;border-radius:12px;'
        . 'font:inherit;font-weight:700;color:#fff;cursor:pointer;background:linear-gradient(135deg,#6366f1,#8b5cf6);'
        . 'box-shadow:0 14px 30px rgba(79,70,229,.35);width:100%}'
        . '.btn:hover{filter:brightness(1.06)}'
        . '.alert{padding:12px 15px;border-radius:11px;font-size:.88rem;margin:0 0 14px;border:1px solid}'
        . '.alert.err{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.35);color:#fca5a5}'
        . '.alert.ok{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.35);color:#6ee7b7}'
        . '.alert.warn{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.35);color:#fcd34d}'
        . 'code{font-family:ui-monospace,Menlo,Consolas,monospace;background:rgba(148,163,184,.12);padding:2px 6px;border-radius:6px;font-size:.86em}'
        . 'pre{background:#060b16;border:1px solid rgba(148,163,184,.2);border-radius:12px;padding:14px;overflow:auto;font-size:.8rem}'
        . '.muted{color:#7f93b3;font-size:.8rem}'
        . '.step{display:inline-flex;align-items:center;gap:8px;font-size:.76rem;text-transform:uppercase;letter-spacing:.09em;color:#a5b4fc;font-weight:700;margin-bottom:10px}'
        . 'table{width:100%;border-collapse:collapse;font-size:.88rem}td{padding:8px 0;border-bottom:1px dashed rgba(148,163,184,.18)}'
        . 'td:last-child{text-align:right;color:#dbe4f2}'
        . '.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}'
        . '.actions a{flex:1;min-width:180px;text-align:center;padding:12px 18px;border-radius:12px;border:1px solid rgba(148,163,184,.24);'
        . 'text-decoration:none;font-weight:600;font-size:.92rem;color:#e2e8f0}'
        . '.actions a.primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent}'
        . '</style></head><body><div class="wrap"><div class="brand"><span>▶</span> PayAccess setup</div>';
}

function installer_foot()
{
    echo '<p class="muted" style="text-align:center;margin-top:22px">PayAccess v' . e(APP_VERSION) . ' · Core PHP + MySQL · Razorpay checkout</p>'
        . '</div></body></html>';
}

/* -------------------------------------------------------------------------
 * Locked screen
 * ---------------------------------------------------------------------- */
if ($locked && !$installed) {
    installer_head('Already installed');
    echo '<div class="card" style="max-width:620px;margin:0 auto;text-align:center">'
        . '<h1>PayAccess is already installed</h1>'
        . '<p>The installer is locked to protect your data (<code>install.lock</code> exists).</p>'
        . '<div class="alert warn">To reinstall, delete <code>install.lock</code> and <code>install.php</code>. '
        . 'Your database tables are never deleted by this installer.</div>'
        . '<div class="actions" style="justify-content:center">'
        . '<a class="primary" href="admin/login.php">Open admin panel</a>'
        . '<a href="./">View site</a>'
        . '</div></div>';
    installer_foot();
    exit;
}

/* -------------------------------------------------------------------------
 * Success screen
 * ---------------------------------------------------------------------- */
if ($installed) {
    installer_head('Installation complete');
    echo '<div class="card" style="max-width:680px;margin:0 auto">'
        . '<div class="step">Step 3 of 3 · done</div>'
        . '<h1>Installation complete 🎉</h1>'
        . '<p>PayAccess is ready. Sign in to add your Razorpay keys, upload a logo and set the video link.</p>';

    if (!$configWritten) {
        echo '<div class="alert warn"><strong>Action required:</strong> the installer could not write '
            . '<code>config.local.php</code> (folder not writable). Create the file manually with the content below.</div>'
            . '<pre>' . e($configContent) . '</pre>';
    }

    echo '<h2>Your admin account</h2>'
        . '<table><tr><td>Username</td><td><code>' . e($values['username']) . '</code></td></tr>'
        . '<tr><td>Password</td><td>the one you just entered</td></tr>'
        . '<tr><td>Dashboard</td><td><code>admin/login.php</code></td></tr></table>';

    echo '<div class="alert err" style="margin-top:18px"><strong>Important:</strong> delete '
        . '<code>install.php</code> from your server now. The installer is locked, but deleting it removes the risk entirely.</div>';

    echo '<h2>Next steps</h2><ol style="color:#a9b8ce;font-size:.9rem;padding-left:20px">'
        . '<li>Open the admin panel and go to <strong>Site settings → Payments</strong> to add your Razorpay keys.</li>'
        . '<li>Upload your logo under <strong>Branding</strong>.</li>'
        . '<li>Add your unlisted YouTube video URL under <strong>Video content</strong>.</li>'
        . '<li>Place a test payment (Demo mode is available) and confirm the video page unlocks.</li>'
        . '</ol>';

    echo '<div class="actions"><a class="primary" href="admin/login.php">Sign in to the admin panel</a>'
        . '<a href="./">Open the public site</a></div></div>';
    installer_foot();
    exit;
}

/* -------------------------------------------------------------------------
 * Setup form
 * ---------------------------------------------------------------------- */
installer_head('Install PayAccess');
?>
<div class="card">
    <div class="step">Step 1 of 3 · requirements</div>
    <h1>Welcome to PayAccess</h1>
    <p>This wizard creates <code>config.local.php</code>, the MySQL tables and your admin account. It takes about a minute.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert err">
            <strong>Please fix the following:</strong>
            <ul style="margin:8px 0 0;padding-left:18px">
                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid">
        <div>
            <h2>Server requirements</h2>
            <ul class="checks">
                <?php foreach ($checks as $check): ?>
                    <li class="<?= $check['ok'] ? 'ok' : ($check['required'] ? 'bad' : 'opt') ?>">
                        <span class="dot"><?= $check['ok'] ? '✓' : ($check['required'] ? '!' : '·') ?></span>
                        <span><strong><?= e($check['label']) ?></strong><br><span class="muted"><?= e($check['detail']) ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (is_file($configFile)): ?>
                <div class="alert warn" style="margin-top:16px">
                    <code>config.local.php</code> already exists and will be overwritten with the values below.
                </div>
            <?php endif; ?>
        </div>

        <div>
            <form method="post" autocomplete="off">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

                <div class="step">Step 2 of 3 · database &amp; admin</div>

                <h2 style="margin-top:6px">MySQL connection</h2>
                <div class="field row">
                    <div>
                        <label for="db_host">Host</label>
                        <input id="db_host" name="db_host" value="<?= e($values['db_host']) ?>" required>
                    </div>
                    <div>
                        <label for="db_port">Port</label>
                        <input id="db_port" name="db_port" value="<?= e($values['db_port']) ?>" required>
                    </div>
                </div>
                <div class="field">
                    <label for="db_name">Database name</label>
                    <input id="db_name" name="db_name" value="<?= e($values['db_name']) ?>" required>
                    <span class="muted">Created automatically if it does not exist.</span>
                </div>
                <div class="field row">
                    <div>
                        <label for="db_user">Username</label>
                        <input id="db_user" name="db_user" value="<?= e($values['db_user']) ?>" required>
                    </div>
                    <div>
                        <label for="db_pass">Password</label>
                        <input id="db_pass" name="db_pass" type="password" value="<?= e($values['db_pass']) ?>">
                    </div>
                </div>

                <h2>Store</h2>
                <div class="field">
                    <label for="site_name">Site name</label>
                    <input id="site_name" name="site_name" value="<?= e($values['site_name']) ?>" maxlength="120" required>
                </div>
                <div class="field row3">
                    <div>
                        <label for="amount">Price (₹)</label>
                        <input id="amount" name="amount" type="number" min="1" step="0.01" value="<?= e($values['amount']) ?>" required>
                    </div>
                    <div>
                        <label for="access_days">Access days</label>
                        <input id="access_days" name="access_days" type="number" min="1" max="3650" value="<?= e($values['access_days']) ?>" required>
                    </div>
                    <div>
                        <label for="demo_mode">Demo mode</label>
                        <input id="demo_mode" name="demo_mode" type="number" min="0" max="1" value="<?= e($values['demo_mode']) ?>" title="1 = on, 0 = off">
                    </div>
                </div>
                <div class="field">
                    <label for="site_url">Site URL <span class="muted">(optional)</span></label>
                    <input id="site_url" name="site_url" value="<?= e($values['site_url']) ?>" placeholder="Leave empty to auto-detect">
                </div>

                <h2>Admin account</h2>
                <div class="field row">
                    <div>
                        <label for="username">Username</label>
                        <input id="username" name="username" value="<?= e($values['username']) ?>" required>
                    </div>
                    <div>
                        <label for="admin_name">Full name</label>
                        <input id="admin_name" name="admin_name" value="<?= e($values['admin_name']) ?>" required>
                    </div>
                </div>
                <div class="field">
                    <label for="admin_email">E-mail</label>
                    <input id="admin_email" name="admin_email" type="email" value="<?= e($values['admin_email']) ?>" required>
                </div>
                <div class="field row">
                    <div>
                        <label for="admin_password">Password</label>
                        <input id="admin_password" name="admin_password" type="password" minlength="8" required>
                    </div>
                    <div>
                        <label for="admin_password_confirm">Confirm</label>
                        <input id="admin_password_confirm" name="admin_password_confirm" type="password" minlength="8" required>
                    </div>
                </div>
                <p class="muted" style="margin-top:-4px">At least 8 characters, with a letter and a number.</p>

                <label style="display:flex;gap:9px;align-items:center;margin:14px 0 4px;font-weight:500">
                    <input type="checkbox" name="seed_demo" value="1" style="width:16px;height:16px" <?= $seedDemo ? 'checked' : '' ?>>
                    Add a few sample payments to explore the dashboard
                </label>

                <button type="submit" class="btn" style="margin-top:18px" <?= $requirementsOk ? '' : 'disabled' ?>>
                    Install PayAccess →
                </button>
                <p class="muted" style="margin-top:10px">Step 3 of 3 happens after you submit.</p>
            </form>
        </div>
    </div>
</div>
<?php
installer_foot();
