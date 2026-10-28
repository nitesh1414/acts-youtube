<?php
/**
 * Payment records: creation, verification updates, access tokens, listing and
 * reporting queries used by the admin panel.
 */
final class Payments
{
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';

    /** Whitelisted sortable columns. */
    const SORTABLE = ['id', 'user_name', 'user_email', 'user_mobile', 'amount', 'status', 'created_at', 'paid_at'];

    /** Whitelisted status filters. */
    const STATUSES = [self::STATUS_PENDING, self::STATUS_SUCCESS, self::STATUS_FAILED];

    /** @var DatabaseInterface */
    private $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // -------------------------------------------------------------------------
    // Create / read
    // -------------------------------------------------------------------------

    /**
     * Insert a pending payment and return its id.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data)
    {
        $row = [
            'user_name'    => db_limit($data['user_name'], 120),
            'user_email'   => db_limit(strtolower($data['user_email']), 190),
            'user_mobile'  => db_limit(normalize_mobile($data['user_mobile']), 20),
            'amount'       => number_format((float) $data['amount'], 2, '.', ''),
            'currency'     => isset($data['currency']) ? db_limit($data['currency'], 3) : 'INR',
            'status'       => isset($data['status']) && in_array($data['status'], self::STATUSES, true) ? $data['status'] : self::STATUS_PENDING,
            'order_id'     => isset($data['order_id']) ? db_limit($data['order_id'], 64) : null,
            'payment_id'   => isset($data['payment_id']) ? db_limit($data['payment_id'], 64) : null,
            'ip_address'   => db_limit(client_ip(), 45),
            'user_agent'   => db_limit(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'cli', 255),
            'created_at'   => now(),
            'updated_at'   => now(),
        ];

        return (int) $this->db->insert('payments', $row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find($id)
    {
        return $this->db->first('SELECT * FROM payments WHERE id = ? LIMIT 1', [(int) $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByOrderId($orderId)
    {
        return $this->db->first('SELECT * FROM payments WHERE order_id = ? LIMIT 1', [(string) $orderId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPaymentId($paymentId)
    {
        return $this->db->first('SELECT * FROM payments WHERE payment_id = ? LIMIT 1', [(string) $paymentId]);
    }

    /**
     * Find a payment by its access token.
     *
     * @return array<string, mixed>|null
     */
    public function findByAccessToken($token)
    {
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
            return null;
        }
        return $this->db->first('SELECT * FROM payments WHERE access_token = ? LIMIT 1', [$token]);
    }

    /**
     * Latest successful payment matching e-mail + mobile (access restore).
     *
     * @return array<string, mixed>|null
     */
    public function findByEmailAndMobile($email, $mobile)
    {
        return $this->db->first(
            'SELECT * FROM payments WHERE user_email = ? AND user_mobile = ? AND status = ? ORDER BY paid_at DESC, id DESC LIMIT 1',
            [strtolower(trim((string) $email)), normalize_mobile($mobile), self::STATUS_SUCCESS]
        );
    }

    // -------------------------------------------------------------------------
    // Status transitions
    // -------------------------------------------------------------------------

    /**
     * Mark a payment as paid and store the Razorpay details.
     *
     * @param array<string, mixed> $data
     */
    public function markSuccess($id, array $data = [])
    {
        $update = [
            'status'     => self::STATUS_SUCCESS,
            'paid_at'    => now(),
            'updated_at' => now(),
        ];
        foreach (['payment_id', 'order_id', 'signature', 'method', 'notes'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $update[$key] = db_limit($data[$key], $key === 'notes' ? 255 : 191);
            }
        }
        $this->db->update('payments', $update, 'id = ?', [(int) $id]);
    }

    /**
     * Mark a payment as failed.
     */
    public function markFailed($id, $reason = '')
    {
        $update = ['status' => self::STATUS_FAILED, 'updated_at' => now()];
        if ($reason !== '') {
            $update['notes'] = db_limit($reason, 255);
        }
        $this->db->update('payments', $update, 'id = ?', [(int) $id]);
    }

    /**
     * Create (or refresh) the access token for a paid payment.
     *
     * @return array{token:string, expires_at:string}
     */
    public function grantAccess($id, $days)
    {
        $token = random_token(32);
        $expiresAt = date('Y-m-d H:i:s', time() + max(1, (int) $days) * 86400);

        $this->db->update(
            'payments',
            ['access_token' => $token, 'access_expires_at' => $expiresAt, 'updated_at' => now()],
            'id = ?',
            [(int) $id]
        );

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Is an access token still valid?
     */
    public static function accessIsValid(array $payment)
    {
        if ((string) $payment['status'] !== self::STATUS_SUCCESS) {
            return false;
        }
        if (empty($payment['access_expires_at'])) {
            return false;
        }
        return strtotime((string) $payment['access_expires_at']) >= time();
    }

    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    /**
     * Paginated, filtered, sorted list.
     *
     * @param  array<string, mixed> $filters q, status, from, to
     * @return array{rows:array<int,array<string,mixed>>, total:int, page:int, per_page:int, pages:int}
     */
    public function paginate(array $filters = [], $sort = 'created_at', $direction = 'desc', $page = 1, $perPage = 15)
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';
        $direction = strtolower((string) $direction) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int) $page);
        $perPage = max(1, min(100, (int) $perPage));

        list($where, $params) = $this->buildFilters($filters);

        $total = (int) $this->db->value('SELECT COUNT(*) FROM payments ' . $where, $params, 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->all(
            sprintf(
                'SELECT * FROM payments %s ORDER BY %s %s, id %s LIMIT %d OFFSET %d',
                $where,
                $sort,
                $direction,
                $direction,
                $perPage,
                $offset
            ),
            $params
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * Rows matching the filters, without pagination (CSV export).
     *
     * @param  array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function exportRows(array $filters = [])
    {
        list($where, $params) = $this->buildFilters($filters);
        return $this->db->all('SELECT * FROM payments ' . $where . ' ORDER BY created_at DESC, id DESC', $params);
    }

    /**
     * Totals for the current filter set (shown above the payment table).
     *
     * @param  array<string, mixed> $filters
     * @return array{success_count:int, revenue:float, pending_count:int}
     */
    public function totals(array $filters = [])
    {
        list($where, $params) = $this->buildFilters($filters);
        $row = $this->db->first(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END), 0) AS success_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) AS revenue
             FROM payments " . $where,
            $params
        );

        return [
            'success_count' => is_array($row) ? (int) $row['success_count'] : 0,
            'pending_count' => is_array($row) ? (int) $row['pending_count'] : 0,
            'revenue'       => is_array($row) ? (float) $row['revenue'] : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed> $filters
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function buildFilters(array $filters)
    {
        $clauses = [];
        $params = [];

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $like = like_term($q);
            $clauses[] = '(user_name LIKE ? OR user_email LIKE ? OR user_mobile LIKE ? OR order_id LIKE ? OR payment_id LIKE ?)';
            for ($i = 0; $i < 5; $i++) {
                $params[] = $like;
            }
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $clauses[] = 'status = ?';
            $params[] = $status;
        }

        $from = isset($filters['from']) ? trim((string) $filters['from']) : '';
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $clauses[] = 'created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }

        $to = isset($filters['to']) ? trim((string) $filters['to']) : '';
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $clauses[] = 'created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /**
     * Summary statistics for the dashboard.
     *
     * Day/week boundaries are computed in PHP (app timezone) so the numbers do
     * not shift when MySQL runs with a different time zone.
     *
     * @return array<string, mixed>
     */
    public function stats()
    {
        $now = now();
        $todayStart = date('Y-m-d 00:00:00');
        $weekStart = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $monthStart = date('Y-m-d 00:00:00', strtotime('-29 days'));

        $row = $this->db->first(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END), 0) AS success_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) AS failed_count,
                COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN status = 'success' AND paid_at >= ? THEN amount ELSE 0 END), 0) AS revenue_today,
                COALESCE(SUM(CASE WHEN status = 'success' AND paid_at >= ? THEN amount ELSE 0 END), 0) AS revenue_week,
                COALESCE(SUM(CASE WHEN status = 'success' AND paid_at >= ? THEN amount ELSE 0 END), 0) AS revenue_month,
                COUNT(DISTINCT CASE WHEN status = 'success' THEN user_email END) AS customers,
                COALESCE(SUM(CASE WHEN status = 'success' AND access_expires_at > ? THEN 1 ELSE 0 END), 0) AS active_access
            FROM payments",
            [$todayStart, $weekStart, $monthStart, $now]
        );

        $stats = [
            'total'          => 0,
            'success_count'  => 0,
            'pending_count'  => 0,
            'failed_count'   => 0,
            'revenue'        => 0.0,
            'revenue_today'  => 0.0,
            'revenue_week'   => 0.0,
            'revenue_month'  => 0.0,
            'customers'      => 0,
            'active_access'  => 0,
            'conversion'     => 0.0,
        ];

        if (is_array($row)) {
            foreach ($stats as $key => $default) {
                if (isset($row[$key])) {
                    $stats[$key] = in_array($key, ['total', 'success_count', 'pending_count', 'failed_count', 'customers', 'active_access'], true)
                        ? (int) $row[$key]
                        : (float) $row[$key];
                }
            }
        }

        if ($stats['total'] > 0) {
            $stats['conversion'] = round($stats['success_count'] * 100 / $stats['total'], 1);
        }

        return $stats;
    }

    /**
     * Successful payments per day for the last N days (missing days filled in).
     *
     * @return array<int, array{day:string, label:string, total:float, count:int}>
     */
    public function dailyRevenue($days = 14)
    {
        $days = max(1, min(90, (int) $days));
        $start = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        $rows = $this->db->all(
            "SELECT DATE(paid_at) AS day, COUNT(*) AS payments, COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE status = 'success' AND paid_at IS NOT NULL AND paid_at >= ?
             GROUP BY DATE(paid_at)
             ORDER BY day ASC",
            [$start]
        );

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = $row;
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $key = date('Y-m-d', strtotime('-' . $i . ' days'));
            $row = isset($byDay[$key]) ? $byDay[$key] : null;
            $series[] = [
                'day'   => $key,
                'label' => date('d M', strtotime($key)),
                'total' => $row ? (float) $row['total'] : 0.0,
                'count' => $row ? (int) $row['payments'] : 0,
            ];
        }

        return $series;
    }

    /**
     * The most recent payments.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent($limit = 8)
    {
        return $this->db->all('SELECT * FROM payments ORDER BY created_at DESC, id DESC LIMIT ' . max(1, (int) $limit));
    }

    /**
     * Timeline entries for a payment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function events($paymentId)
    {
        return $this->db->all(
            'SELECT * FROM payment_events WHERE payment_id = ? ORDER BY created_at ASC, id ASC',
            [(int) $paymentId]
        );
    }

    /**
     * Record an audit event for a payment.
     *
     * @param mixed $payload
     */
    public function logEvent($paymentId, $event, $message = '', $payload = null)
    {
        try {
            $this->db->insert('payment_events', [
                'payment_id' => $paymentId === null ? null : (int) $paymentId,
                'event'      => db_limit($event, 50),
                'message'    => db_limit($message, 255),
                'payload'    => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            app_log('warning', 'Could not log payment event: ' . $e->getMessage());
        }
    }
}
