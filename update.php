<?php
/**
 * PayAccess — database updater.
 *
 * Safe to run on any version: it only ADDS missing tables, columns, indexes and
 * the default settings row. Existing payments, settings and admin accounts are
 * never modified or deleted.
 *
 * Use it after updating the files (especially when coming from v1, which had a
 * different schema), or whenever the site shows "Database update required".
 *
 * DELETE this file once the site runs correctly.
 */

// Allow the app to boot while the database schema is still behind.
define('PAYACCESS_SKIP_SCHEMA_CHECK', true);

require_once __DIR__ . '/lib/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/* -------------------------------------------------------------------------
 * Configuration + database connection
 * ---------------------------------------------------------------------- */
// __DIR__, not APP_ROOT: config.php has not been loaded yet at this point.
// If something already booted the app (another include, or a test), remember its
// connection before `$db = null` below clears the global — at global scope `$db`
// and `$GLOBALS['db']` are the same variable.
$existingDb = isset($GLOBALS['db']) && is_object($GLOBALS['db']) ? $GLOBALS['db'] : null;

$configFile = __DIR__ . '/config.local.php';
$configError = '';

if (!is_file($configFile)) {
    $configError = 'config.local.php was not found. Run install.php first.';
}

$db = null;
if ($configError === '') {
    require_once __DIR__ . '/config.php'; // defines DB_* and boots the app
    $db = isset($GLOBALS['db']) && is_object($GLOBALS['db']) ? $GLOBALS['db'] : $existingDb;
} else {
    require_once __DIR__ . '/lib/db.php';
    require_once __DIR__ . '/lib/schema.php';
}

// Only true when config.php never ran (no config.local.php): give the page the
// few constants it needs. Never redefine what the app already defined.
defined('APP_ROOT')    || define('APP_ROOT', __DIR__);
defined('APP_VERSION') || define('APP_VERSION', '2.0.0');

/* -------------------------------------------------------------------------
 * State
 * ---------------------------------------------------------------------- */
$report = null;
$errors = [];
$adminCreated = false;
$adminError = '';
$probe = null; // result of the "test the payment write" action
$hasSession = session_status() === PHP_SESSION_ACTIVE;

if (empty($_SESSION['update_csrf'])) {
    $_SESSION['update_csrf'] = bin2hex(random_bytes(24));
}
$csrfToken = (string) $_SESSION['update_csrf'];

$missing = [];
$adminCount = 0;
$tablesOk = false;

if ($db !== null) {
    try {
        $missing = Schema::missing($db);
        $tablesOk = true;
        $adminCount = (int) $db->value('SELECT COUNT(*) FROM admin_users', [], 0);
    } catch (Throwable $e) {
        $missing = ['the database could not be inspected: ' . $e->getMessage()];
    }
}

/* -------------------------------------------------------------------------
 * Actions
 * ---------------------------------------------------------------------- */
$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $db !== null) {
    $token = isset($_POST['_token']) ? (string) $_POST['_token'] : '';

    if (!$hasSession || !hash_equals($csrfToken, $token)) {
        $errors[] = 'Your session expired. Please reload this page and try again.';
    } elseif ($action === 'migrate') {
        try {
            $report = Schema::migrate($db, function ($message) {
                echo '<li>' . e($message) . '</li>';
            });
            // Re-read state after the migration.
            $missing = Schema::missing($db);
            try {
                $adminCount = (int) $db->value('SELECT COUNT(*) FROM admin_users', [], 0);
            } catch (Throwable $e) {
                $adminCount = 0;
            }
        } catch (Throwable $e) {
            $errors[] = 'The update failed: ' . $e->getMessage();
        }
    } elseif ($action === 'test_write') {
        $probe = ['ok' => false, 'message' => '', 'detail' => '', 'ref' => ''];
        $probeId = null;

        try {
            // A real INSERT, rolled back immediately: it proves the payments
            // table accepts exactly what checkout will send.
            $db->transaction(function () use ($db, &$probeId) {
                // 1. The pending row checkout creates first.
                $probeId = app()->payments->create([
                    'user_name'   => 'Update check',
                    'user_email'  => 'probe@example.invalid',
                    'user_mobile' => '9000000000',
                    'amount'      => 1,
                    'currency'    => 'INR',
                ]);

                // 2. The audit entry written right after it.
                app()->payments->logEvent((int) $probeId, 'probe.update_check', 'Diagnostic row', ['amount' => 1]);

                // 3. The UPDATE that attaches the Razorpay order id — this is the
                //    statement that used to fail with "SQLSTATE[HY093]".
                $db->update('payments', ['order_id' => '__update_probe__', 'updated_at' => now()], 'id = ?', [(int) $probeId]);

                throw new RuntimeException('__rollback__');
            });
            $probe['ok'] = true;
        } catch (Throwable $e) {
            if ($e->getMessage() === '__rollback__') {
                $probe['ok'] = true;
            } else {
                $probe['message'] = database_error_hint($e, $ref);
                $probe['ref'] = (string) $ref;
                $probe['detail'] = $e->getMessage();
            }
        }

        // MyISAM (or a failed rollback) leaves the probe row behind — remove it.
        if ($probeId !== null) {
            try {
                $db->exec('DELETE FROM `payments` WHERE `id` = ?', [(int) $probeId]);
            } catch (Throwable $e) {
                // Nothing else to do; the row is harmless and clearly labelled.
            }
        }

        if ($probe['ok']) {
            $probe['message'] = 'The payments table accepted a test row (rolled back). Checkout writes will work.';
        }
    } elseif ($action === 'create_admin') {
        if ($adminCount > 0) {
            $adminError = 'Admin accounts already exist. Sign in at admin/login.php and add users there.';
        } else {
            $username = strtolower(trim((string) ($_POST['username'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,31}$/', $username)) {
                $adminError = 'The username must be 3-32 characters: letters, numbers, dot, dash or underscore.';
            } elseif ($name === '') {
                $adminError = 'Enter the admin full name.';
            } elseif (!valid_email($email)) {
                $adminError = 'Enter a valid e-mail address.';
            } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $adminError = 'The password must be at least 8 characters and contain a letter and a number.';
            } elseif ($password !== $confirm) {
                $adminError = 'The two passwords do not match.';
            } else {
                try {
                    $db->insert('admin_users', [
                        'username'      => $username,
                        'name'          => db_limit($name, 100),
                        'email'         => db_limit($email, 190),
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'is_active'     => 1,
                        'created_at'    => date('Y-m-d H:i:s'),
                        'updated_at'    => date('Y-m-d H:i:s'),
                    ]);
                    $adminCreated = true;
                    $adminCount = 1;
                    unset($_SESSION['update_csrf']);
                } catch (Throwable $e) {
                    $adminError = 'Could not create the account: ' . $e->getMessage();
                }
            }
        }
    }
}

$schemaCurrent = $db !== null && $missing === [];

/* -------------------------------------------------------------------------
 * Environment (helps diagnose "it still does not work" reports)
 * ---------------------------------------------------------------------- */
$serverVersion = 'unknown';
if ($db !== null) {
    try {
        $serverVersion = (string) $db->value('SELECT VERSION()', [], 'unknown');
    } catch (Throwable $e) {
        $serverVersion = 'unknown';
    }
}

// Last few database problems from storage/logs — the detail behind "Ref XXXXXX".
$recentErrors = [];
$logTail = '';
$logFiles = glob(APP_ROOT . '/storage/logs/app-*.log');
if (is_array($logFiles) && $logFiles !== []) {
    rsort($logFiles);
    $lines = @file($logFiles[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach (array_slice(array_reverse($lines), 0, 400) as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                continue;
            }
            $text = isset($entry['message']) ? (string) $entry['message'] : '';
            $level = isset($entry['level']) ? (string) $entry['level'] : '';
            if ($level !== 'error' && $level !== 'critical'
                && stripos($text, 'database') === false && stripos($text, 'SQLSTATE') === false) {
                continue;
            }
            $recentErrors[] = [
                'time'    => isset($entry['time']) ? date('d M Y, H:i:s', strtotime((string) $entry['time'])) : '',
                'level'   => $level,
                'message' => text_limit($text, 400),
            ];
            if (count($recentErrors) >= 5) {
                break;
            }
        }
    }
    $tail = array_slice($lines, -25);
    $logTail = implode("\n", $tail);
}

$environment = [
    ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>=')],
    ['MySQL/MariaDB ' . $serverVersion, $db !== null],
    ['PDO MySQL', extension_loaded('pdo_mysql')],
    ['Razorpay HTTPS client', function_exists('curl_init') || (bool) ini_get('allow_url_fopen')],
    ['uploads/ writable', is_dir(APP_ROOT . '/uploads') && is_writable(APP_ROOT . '/uploads')],
    ['storage/ writable', is_dir(APP_ROOT . '/storage') && is_writable(APP_ROOT . '/storage')],
];

/* -------------------------------------------------------------------------
 * View
 * ---------------------------------------------------------------------- */
function update_head($title)
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . e($title) . '</title><style>'
        . ':root{color-scheme:dark}*{box-sizing:border-box}'
        . 'body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;min-height:100vh;padding:34px 16px;'
        . 'background:radial-gradient(45% 45% at 20% 10%,#1e293b,#070b14 65%);color:#e2e8f0;line-height:1.6}'
        . '.wrap{max-width:760px;margin:0 auto}'
        . '.brand{display:flex;align-items:center;gap:12px;margin-bottom:22px;font-weight:700;font-size:1.1rem}'
        . '.brand span{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,#6366f1,#8b5cf6)}'
        . '.card{background:rgba(17,28,51,.78);border:1px solid rgba(148,163,184,.18);border-radius:20px;padding:30px;'
        . 'box-shadow:0 30px 70px rgba(2,6,18,.5)}'
        . 'h1{font-size:1.5rem;margin:0 0 8px;letter-spacing:-.02em}h2{font-size:1.02rem;margin:26px 0 10px}'
        . 'p{margin:0 0 12px;color:#a9b8ce}a{color:#a5b4fc}'
        . '.btn{display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border:0;border-radius:12px;font:inherit;'
        . 'font-weight:700;color:#fff;cursor:pointer;background:linear-gradient(135deg,#6366f1,#8b5cf6);'
        . 'box-shadow:0 14px 30px rgba(79,70,229,.35)}.btn:hover{filter:brightness(1.06)}'
        . '.btn--ghost{background:rgba(148,163,184,.12);box-shadow:none;border:1px solid rgba(148,163,184,.24)}'
        . '.alert{padding:12px 15px;border-radius:11px;font-size:.9rem;margin:0 0 14px;border:1px solid}'
        . '.alert.err{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.35);color:#fca5a5}'
        . '.alert.ok{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.35);color:#6ee7b7}'
        . '.alert.warn{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.35);color:#fcd34d}'
        . 'code{font-family:ui-monospace,Menlo,Consolas,monospace;background:rgba(148,163,184,.12);padding:2px 6px;'
        . 'border-radius:6px;font-size:.86em;word-break:break-word}'
        . 'ul.checks{list-style:none;padding:0;margin:0 0 18px;display:grid;gap:8px}'
        . 'ul.checks li{padding:8px 12px;border-radius:9px;border:1px solid rgba(148,163,184,.16);'
        . 'background:rgba(148,163,184,.05);font-size:.87rem}'
        . 'ul.checks li.bad{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.08);color:#fca5a5}'
        . '.log{list-style:none;padding:0;margin:10px 0 0;font-size:.84rem;color:#a9b8ce;display:grid;gap:4px}'
        . '.log li::before{content:"✓ ";color:#34d399}'
        . 'label{display:block;font-size:.82rem;font-weight:600;margin:0 0 5px;color:#dbe4f2}'
        . 'input{width:100%;padding:10px 13px;border-radius:10px;border:1px solid rgba(148,163,184,.24);'
        . 'background:rgba(4,9,20,.6);color:#e8eefb;font:inherit;font-size:.92rem}'
        . 'input:focus{outline:none;border-color:#818cf8;box-shadow:0 0 0 3px rgba(99,102,241,.2)}'
        . '.field{margin-bottom:14px}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}'
        . '@media(max-width:640px){.row{grid-template-columns:1fr}}'
        . '.muted{color:#7f93b3;font-size:.82rem}'
        . '</style></head><body><div class="wrap"><div class="brand"><span>▶</span> PayAccess database update</div>';
}

function update_foot()
{
    echo '<p class="muted" style="text-align:center;margin-top:20px">PayAccess v' . e(APP_VERSION)
        . ' · additive migrations only — no data is deleted</p></div></body></html>';
}

update_head('Database update');
?>
<div class="card">
    <h1>Database update</h1>
    <p>This tool adds anything the current version needs — tables, columns, indexes and the
        default settings row. It never deletes or rewrites your payments, settings or admins.</p>

    <?php if ($configError !== ''): ?>
        <div class="alert err"><?= e($configError) ?></div>
        <p><a class="btn" href="install.php">Open the installer</a></p>
    <?php else: ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert err"><?= e($error) ?></div>
        <?php endforeach; ?>

        <?php if (!$hasSession): ?>
            <div class="alert warn">PHP sessions are not available on this server, so the update cannot be confirmed safely. Fix <code>session.save_path</code> and reload.</div>
        <?php endif; ?>

        <h2>Current status</h2>
        <?php if (!$tablesOk): ?>
            <ul class="checks">
                <?php foreach ($missing as $item): ?>
                    <li class="bad"><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php elseif ($schemaCurrent): ?>
            <div class="alert ok"><strong>Everything is up to date.</strong> All required tables and columns exist.</div>
        <?php else: ?>
            <ul class="checks">
                <?php foreach ($missing as $item): ?>
                    <li class="bad">
                        <?= strpos($item, 'must allow NULL') !== false ? 'Fix:' : 'Missing:' ?> <?= e($item) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h2>Diagnose checkout</h2>
        <p>If paying still fails, press this button. It writes one row to <code>payments</code> and
            rolls it back, so you see the exact error instead of a generic message.</p>
        <form method="post" style="margin:0 0 12px">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="test_write">
            <button type="submit" class="btn btn--ghost" <?= $hasSession ? '' : 'disabled' ?>>Test the payment write</button>
        </form>

        <?php if ($probe !== null): ?>
            <div class="alert <?= $probe['ok'] ? 'ok' : 'err' ?>"><?= e($probe['message']) ?></div>
            <?php if (!$probe['ok'] && $probe['detail'] !== ''): ?>
                <div class="alert warn"><strong>MySQL said:</strong> <code><?= e($probe['detail']) ?></code></div>
                <p class="muted">Reference <code><?= e($probe['ref']) ?></code> — search for it in
                    <code>storage/logs</code>.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($logTail !== ''): ?>
            <h2>Latest log lines</h2>
            <p class="muted">Copy this if you need help. Nothing here is secret except your database
                password, which is never written to the log.</p>
            <textarea readonly rows="12" style="width:100%;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.78rem;"
                onclick="this.select()"><?= e($logTail) ?></textarea>
        <?php endif; ?>

        <?php if ($recentErrors !== []): ?>
            <h2>Recent database errors</h2>
            <ul class="log">
                <?php foreach ($recentErrors as $entry): ?>
                    <li style="list-style:none">
                        <span class="muted"><?= e($entry['time']) ?></span>
                        <code><?= e($entry['message']) ?></code>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <details style="margin:18px 0 0">
            <summary style="cursor:pointer;font-size:.88rem;color:#a5b4fc">Environment check</summary>
            <ul class="checks" style="margin-top:10px">
                <?php foreach ($environment as $item): ?>
                    <li class="<?= $item[1] ? '' : 'bad' ?>">
                        <?= $item[1] ? '✓' : '✗' ?> <?= e($item[0]) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="muted">Errors are written to <code>storage/logs</code>. If the public site or the
                Pay button still fails after the update, check that log first.</p>
        </details>

        <?php if ($report !== null): ?>
            <div class="alert <?= $report['ok'] ? 'ok' : 'err' ?>">
                <?= $report['ok'] ? 'Update finished.' : 'The update finished with problems — see below.' ?>
            </div>
            <ul class="log">
                <?php foreach ($report['tables_created'] as $table): ?>
                    <li>Created table <code><?= e($table) ?></code></li>
                <?php endforeach; ?>
                <?php foreach ($report['columns_added'] as $column): ?>
                    <li>Added column <code><?= e($column) ?></code></li>
                <?php endforeach; ?>
                <?php foreach ($report['indexes_added'] as $index): ?>
                    <li>Added index <code><?= e($index) ?></code></li>
                <?php endforeach; ?>
                <?php foreach (($report['columns_relaxed'] ?? []) as $relaxed): ?>
                    <li>Allowed NULL for <code><?= e($relaxed) ?></code> (old table required a value)</li>
                <?php endforeach; ?>
                <?php foreach ($report['data_changes'] as $change): ?>
                    <li><?= e($change) ?></li>
                <?php endforeach; ?>
                <?php if (!$report['tables_created'] && !$report['columns_added'] && !$report['indexes_added']
                        && empty($report['columns_relaxed']) && !$report['data_changes']): ?>
                    <li>Nothing to change — schema was already current.</li>
                <?php endif; ?>
            </ul>
            <?php foreach ($report['errors'] as $error): ?>
                <p class="alert err"><?= e($error) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post" style="margin-top:18px">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="migrate">
            <button type="submit" class="btn" <?= (!$hasSession || $schemaCurrent) ? 'disabled' : '' ?>>
                <?= $schemaCurrent ? 'Database is up to date' : 'Run the update' ?>
            </button>
            <a class="btn btn--ghost" href="admin/login.php">Admin sign-in</a>
            <a class="btn btn--ghost" href="./">Public site</a>
        </form>

        <?php if ($adminCreated): ?>
            <h2>Create the first admin account</h2>
            <div class="alert ok">Admin account created. You can sign in now.</div>
            <p><a class="btn" href="admin/login.php">Go to the admin sign-in</a></p>
        <?php elseif ($adminCount === 0 && $tablesOk): ?>
            <h2>Create the first admin account</h2>
            <p>No admin accounts exist yet (a v1 database had none). Create one to sign in.</p>

            <?php if ($adminError !== ''): ?>
                <div class="alert err"><?= e($adminError) ?></div>
            <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_admin">
                    <div class="row">
                        <div class="field">
                            <label for="username">Username</label>
                            <input id="username" name="username" value="admin" required>
                        </div>
                        <div class="field">
                            <label for="name">Full name</label>
                            <input id="name" name="name" value="Administrator" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="email">E-mail</label>
                        <input id="email" name="email" type="email" required>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" minlength="8" required>
                        </div>
                        <div class="field">
                            <label for="confirm_password">Confirm</label>
                            <input id="confirm_password" name="confirm_password" type="password" minlength="8" required>
                        </div>
                    </div>
                <p class="muted">At least 8 characters with a letter and a number.</p>
                <button type="submit" class="btn" <?= $hasSession ? '' : 'disabled' ?>>Create admin</button>
            </form>
        <?php elseif ($adminCount > 0): ?>
            <p class="muted" style="margin-top:18px"><?= (int) $adminCount ?> admin account(s) found — nothing else to do.</p>
        <?php endif; ?>

        <p class="muted" style="margin-top:24px">
            <strong>Security:</strong> delete <code>update.php</code> (and <code>install.php</code>) once
            everything works. They can only add data, never remove it, but there is no reason to keep them online.
        </p>
    <?php endif; ?>
</div>
<?php
update_foot();
