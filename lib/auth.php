<?php
/**
 * Admin authentication: database-backed accounts, bcrypt hashes, throttled
 * logins, session fingerprinting and idle timeouts.
 */

final class Auth
{
    const SESSION_KEY      = 'admin_id';
    const LAST_SEEN_KEY    = 'admin_last_seen';
    const LOGIN_AT_KEY     = 'admin_login_at';
    const UA_KEY           = 'admin_ua';
    const IDLE_TIMEOUT     = 2700; // 45 minutes
    const LOGIN_MAX_TRIES  = 5;    // per username + IP
    const LOGIN_WINDOW     = 600;  // over 10 minutes
    const IP_MAX_TRIES     = 25;   // per IP
    const MIN_PASSWORD_LEN = 8;

    /** @var DatabaseInterface */
    private $db;

    /** @var Throttle */
    private $throttle;

    /** @var array<string, mixed>|null */
    private $cachedUser = null;

    /** @var bool */
    private $resolved = false;

    public function __construct(DatabaseInterface $db, Throttle $throttle)
    {
        $this->db = $db;
        $this->throttle = $throttle;
    }

    // -------------------------------------------------------------------------
    // Login / logout
    // -------------------------------------------------------------------------

    /**
     * Attempt a login.
     *
     * @return array{ok:bool, error:string, admin:array<string,mixed>|null}
     */
    public function attempt($username, $password, $ip = null)
    {
        $username = strtolower(trim((string) $username));
        $ip = $ip ?: client_ip();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            app_log('error', 'Admin sign-in blocked: PHP sessions are not available.');
            return [
                'ok'    => false,
                'error' => 'Sessions are not available on this server, so sign-in is disabled. '
                    . 'Ask your host to enable PHP sessions (session.save_path must be writable).',
                'admin' => null,
            ];
        }

        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'Enter both username and password.', 'admin' => null];
        }

        $key = $username . '|' . $ip;
        if ($this->throttle->tooMany('admin_login', $key, self::LOGIN_MAX_TRIES, self::LOGIN_WINDOW)
            || $this->throttle->tooMany('admin_login_ip', $ip, self::IP_MAX_TRIES, self::LOGIN_WINDOW)) {
            $wait = max(
                $this->throttle->retryAfter('admin_login', $key, self::LOGIN_WINDOW),
                $this->throttle->retryAfter('admin_login_ip', $ip, self::LOGIN_WINDOW)
            );
            $minutes = max(1, (int) ceil($wait / 60));
            return [
                'ok'    => false,
                'error' => 'Too many failed attempts. Please try again in about ' . $minutes . ' minute(s).',
                'admin' => null,
            ];
        }

        $admin = $this->findByUsername($username);

        // Always run a hash verification to keep the timing similar.
        $hash = $admin && !empty($admin['password_hash']) ? $admin['password_hash'] : '$2y$10$usesomesillystringforsaltxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        $valid = password_verify((string) $password, $hash);

        if (!$admin || !$valid || (int) $admin['is_active'] !== 1) {
            $this->throttle->hit('admin_login', $key);
            $this->throttle->hit('admin_login_ip', $ip);
            app_log('notice', 'Failed admin login', ['username' => $username, 'ip' => $ip]);
            return ['ok' => false, 'error' => 'Invalid username or password.', 'admin' => null];
        }

        // Upgrade the hash if PHP's default algorithm has changed.
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->update(
                'admin_users',
                ['password_hash' => password_hash((string) $password, PASSWORD_DEFAULT)],
                'id = ?',
                [$admin['id']]
            );
        }

        $this->throttle->clear('admin_login', $key);
        $this->throttle->clear('admin_login_ip', $ip);

        $this->startSession($admin);
        $this->db->update(
            'admin_users',
            ['last_login_at' => now(), 'last_login_ip' => db_limit($ip, 45)],
            'id = ?',
            [$admin['id']]
        );

        app_log('info', 'Admin signed in', ['admin_id' => $admin['id'], 'username' => $admin['username'], 'ip' => $ip]);

        return ['ok' => true, 'error' => '', 'admin' => $admin];
    }

    /**
     * Store the authenticated session and rotate the session id.
     */
    private function startSession(array $admin)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION[self::SESSION_KEY] = (int) $admin['id'];
        $_SESSION[self::LOGIN_AT_KEY] = time();
        $_SESSION[self::LAST_SEEN_KEY] = time();
        $_SESSION[self::UA_KEY] = $this->fingerprint();
        csrf_token(); // make sure a fresh token exists
    }

    /**
     * Sign the current admin out.
     */
    public function logout()
    {
        $this->cachedUser = null;
        $this->resolved = true;
        if (session_is_active()) {
            unset($_SESSION[self::SESSION_KEY], $_SESSION[self::LAST_SEEN_KEY], $_SESSION[self::LOGIN_AT_KEY], $_SESSION[self::UA_KEY]);
            $_SESSION['_csrf'] = random_token(32);
        }
    }

    // -------------------------------------------------------------------------
    // Current user
    // -------------------------------------------------------------------------

    public function check()
    {
        return $this->user() !== null;
    }

    /**
     * The signed-in admin record, or null.
     *
     * @return array<string, mixed>|null
     */
    public function user()
    {
        if ($this->resolved) {
            return $this->cachedUser;
        }
        $this->resolved = true;
        $this->cachedUser = null;

        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        // Session fingerprint + idle timeout.
        $fingerprint = isset($_SESSION[self::UA_KEY]) ? (string) $_SESSION[self::UA_KEY] : '';
        $lastSeen = isset($_SESSION[self::LAST_SEEN_KEY]) ? (int) $_SESSION[self::LAST_SEEN_KEY] : 0;
        if ($fingerprint !== '' && !hash_equals($fingerprint, $this->fingerprint())) {
            $this->logout();
            return null;
        }
        if ($lastSeen > 0 && (time() - $lastSeen) > self::IDLE_TIMEOUT) {
            $this->logout();
            return null;
        }

        $admin = $this->find((int) $_SESSION[self::SESSION_KEY]);
        if (!$admin || (int) $admin['is_active'] !== 1) {
            $this->logout();
            return null;
        }

        $_SESSION[self::LAST_SEEN_KEY] = time();
        $this->cachedUser = $admin;
        return $admin;
    }

    /**
     * Require a signed-in admin; redirects to the login page otherwise.
     *
     * @return array<string, mixed>
     */
    public function requireLogin()
    {
        $admin = $this->user();
        if ($admin === null) {
            $next = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $target = 'admin/login.php';
            if ($next !== '' && strpos($next, '//') === false) {
                $target .= '?next=' . rawurlencode($next);
            }
            flash('info', 'Please sign in to continue.');
            redirect($target);
        }
        return $admin;
    }

    /**
     * Session fingerprint (does not identify the device, just detects session theft).
     */
    private function fingerprint()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : 'cli';
        return hash('sha256', $ua . '|' . (defined('APP_NAME') ? APP_NAME : ''));
    }

    // -------------------------------------------------------------------------
    // Accounts
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    public function find($id)
    {
        return $this->db->first('SELECT * FROM admin_users WHERE id = ? LIMIT 1', [(int) $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername($username)
    {
        return $this->db->first('SELECT * FROM admin_users WHERE username = ? LIMIT 1', [strtolower(trim((string) $username))]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function admins()
    {
        return $this->db->all('SELECT * FROM admin_users ORDER BY id ASC');
    }

    public function activeCount()
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM admin_users WHERE is_active = 1', [], 0);
    }

    /**
     * Validate the password policy.
     *
     * @return string error message, or '' when valid
     */
    public static function passwordError($password, $confirmation = null)
    {
        $password = (string) $password;
        if (strlen($password) < self::MIN_PASSWORD_LEN) {
            return 'Password must be at least ' . self::MIN_PASSWORD_LEN . ' characters long.';
        }
        if (strlen($password) > 72) {
            return 'Password must be 72 characters or fewer.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one letter and one number.';
        }
        if ($confirmation !== null && $password !== (string) $confirmation) {
            return 'The two passwords do not match.';
        }
        return '';
    }

    /**
     * Create an admin account.
     *
     * @return array{ok:bool, error:string, id:int}
     */
    public function create($username, $name, $email, $password, $confirmation = null)
    {
        $username = strtolower(trim((string) $username));
        $name = trim((string) $name);
        $email = trim((string) $email);

        if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,31}$/', $username)) {
            return ['ok' => false, 'error' => 'Username must be 3-32 characters: letters, numbers, dot, dash or underscore.', 'id' => 0];
        }
        if ($name === '' || text_length($name) > 100) {
            return ['ok' => false, 'error' => 'Enter the full name of the admin.', 'id' => 0];
        }
        if (!valid_email($email)) {
            return ['ok' => false, 'error' => 'Enter a valid e-mail address.', 'id' => 0];
        }
        $passwordError = self::passwordError($password, $confirmation);
        if ($passwordError !== '') {
            return ['ok' => false, 'error' => $passwordError, 'id' => 0];
        }
        if ($this->findByUsername($username)) {
            return ['ok' => false, 'error' => 'That username is already taken.', 'id' => 0];
        }

        $id = $this->db->insert('admin_users', [
            'username'      => $username,
            'name'          => db_limit($name, 100),
            'email'         => db_limit($email, 190),
            'password_hash' => password_hash((string) $password, PASSWORD_DEFAULT),
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        app_log('info', 'Admin account created', ['admin_id' => $id, 'username' => $username]);

        return ['ok' => true, 'error' => '', 'id' => (int) $id];
    }

    /**
     * Change the password of the signed-in admin.
     *
     * @return array{ok:bool, error:string}
     */
    public function changePassword($adminId, $current, $new, $confirmation)
    {
        $admin = $this->find($adminId);
        if (!$admin) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }
        if (!password_verify((string) $current, $admin['password_hash'])) {
            return ['ok' => false, 'error' => 'Your current password is not correct.'];
        }
        if (password_verify((string) $new, $admin['password_hash'])) {
            return ['ok' => false, 'error' => 'The new password must be different from the current one.'];
        }
        $error = self::passwordError($new, $confirmation);
        if ($error !== '') {
            return ['ok' => false, 'error' => $error];
        }

        $this->db->update(
            'admin_users',
            ['password_hash' => password_hash((string) $new, PASSWORD_DEFAULT), 'updated_at' => now()],
            'id = ?',
            [(int) $adminId]
        );

        app_log('info', 'Admin password changed', ['admin_id' => (int) $adminId]);
        $this->throttle->clear('admin_login', strtolower($admin['username']) . '|' . client_ip());

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Enable or disable an account (never the last active one or yourself).
     *
     * @return array{ok:bool, error:string}
     */
    public function setActive($adminId, $active, $actingAdminId)
    {
        $admin = $this->find($adminId);
        if (!$admin) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }
        if ((int) $adminId === (int) $actingAdminId && !$active) {
            return ['ok' => false, 'error' => 'You cannot deactivate your own account.'];
        }
        if (!$active && $this->activeCount() <= 1) {
            return ['ok' => false, 'error' => 'At least one active admin account is required.'];
        }

        $this->db->update(
            'admin_users',
            ['is_active' => $active ? 1 : 0, 'updated_at' => now()],
            'id = ?',
            [(int) $adminId]
        );
        app_log('info', 'Admin account status changed', ['admin_id' => (int) $adminId, 'active' => (bool) $active]);

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Delete an account (never the last active one or yourself).
     *
     * @return array{ok:bool, error:string}
     */
    public function delete($adminId, $actingAdminId)
    {
        $admin = $this->find($adminId);
        if (!$admin) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }
        if ((int) $adminId === (int) $actingAdminId) {
            return ['ok' => false, 'error' => 'You cannot delete your own account.'];
        }
        if ((int) $admin['is_active'] === 1 && $this->activeCount() <= 1) {
            return ['ok' => false, 'error' => 'At least one active admin account is required.'];
        }

        $this->db->exec('DELETE FROM admin_users WHERE id = ?', [(int) $adminId]);
        app_log('info', 'Admin account deleted', ['admin_id' => (int) $adminId]);

        return ['ok' => true, 'error' => ''];
    }
}
