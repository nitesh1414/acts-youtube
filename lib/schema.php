<?php
/**
 * Database schema: definition, verification and migrations.
 *
 * `Schema::tables()` is the single source of truth. Fresh installs
 * (install.php), upgrades (update.php) and the generated `sql/schema.sql`
 * (tools/build-schema.php) all derive from it, so they can never drift apart.
 *
 * Migrations are deliberately **additive only**: missing tables, columns and
 * indexes are created, existing columns and rows are never dropped or altered.
 */

final class Schema
{
    /** Bump when the definition below changes (invalidates the cached check). */
    const VERSION = 3;

    /**
     * Column definitions, primary key and indexes for every table.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function tables()
    {
        return [
            'settings' => [
                'columns' => [
                    'id'                      => 'TINYINT UNSIGNED NOT NULL DEFAULT 1',
                    'site_name'               => "VARCHAR(120) NOT NULL DEFAULT 'PayAccess'",
                    'logo_path'               => 'VARCHAR(255) DEFAULT NULL',
                    'logo_text'               => "VARCHAR(120) NOT NULL DEFAULT 'PayAccess'",
                    'tagline'                 => "VARCHAR(190) NOT NULL DEFAULT 'Get instant access to our exclusive video'",
                    'amount'                  => 'DECIMAL(10,2) NOT NULL DEFAULT 199.00',
                    'currency'                => "CHAR(3) NOT NULL DEFAULT 'INR'",
                    'razorpay_key_id'         => 'VARCHAR(120) DEFAULT NULL',
                    'razorpay_key_secret'     => 'VARCHAR(190) DEFAULT NULL',
                    'razorpay_webhook_secret' => 'VARCHAR(190) DEFAULT NULL',
                    'youtube_video_url'       => 'VARCHAR(255) DEFAULT NULL',
                    'youtube_channel_url'     => 'VARCHAR(255) DEFAULT NULL',
                    'support_email'           => 'VARCHAR(190) DEFAULT NULL',
                    'support_phone'           => 'VARCHAR(30) DEFAULT NULL',
                    'access_days'             => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
                    'demo_mode'               => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'theme_color'             => "CHAR(7) NOT NULL DEFAULT '#4f46e5'",
                    'updated_at'              => 'DATETIME DEFAULT NULL',
                ],
                'primary' => 'id',
                'indexes' => [],
            ],

            'admin_users' => [
                'columns' => [
                    'id'            => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'username'      => 'VARCHAR(32) NOT NULL',
                    'name'          => 'VARCHAR(100) NOT NULL',
                    'email'         => 'VARCHAR(190) NOT NULL',
                    'password_hash' => 'VARCHAR(255) NOT NULL',
                    'is_active'     => 'TINYINT(1) NOT NULL DEFAULT 1',
                    'last_login_at' => 'DATETIME DEFAULT NULL',
                    'last_login_ip' => 'VARCHAR(45) DEFAULT NULL',
                    'created_at'    => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at'    => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                ],
                'primary' => 'id',
                'indexes' => [
                    'uniq_admin_username' => ['columns' => ['username'], 'unique' => true],
                ],
            ],

            'payments' => [
                'columns' => [
                    'id'                => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'user_name'         => 'VARCHAR(120) NOT NULL',
                    'user_email'        => 'VARCHAR(190) NOT NULL',
                    'user_mobile'       => 'VARCHAR(20) NOT NULL',
                    'amount'            => 'DECIMAL(10,2) NOT NULL',
                    'currency'          => "CHAR(3) NOT NULL DEFAULT 'INR'",
                    'status'            => "ENUM('pending','success','failed') NOT NULL DEFAULT 'pending'",
                    'order_id'          => 'VARCHAR(64) DEFAULT NULL',
                    'payment_id'        => 'VARCHAR(64) DEFAULT NULL',
                    'signature'         => 'VARCHAR(191) DEFAULT NULL',
                    'method'            => 'VARCHAR(32) DEFAULT NULL',
                    'access_token'      => 'CHAR(64) DEFAULT NULL',
                    'access_expires_at' => 'DATETIME DEFAULT NULL',
                    'ip_address'        => 'VARCHAR(45) DEFAULT NULL',
                    'user_agent'        => 'VARCHAR(255) DEFAULT NULL',
                    'notes'             => 'VARCHAR(255) DEFAULT NULL',
                    'product_type'      => 'VARCHAR(10) DEFAULT NULL',
                    'product_id'        => 'INT UNSIGNED DEFAULT NULL',
                    'product_name'      => 'VARCHAR(160) DEFAULT NULL',
                    'created_at'        => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'paid_at'           => 'DATETIME DEFAULT NULL',
                    'updated_at'        => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                ],
                'primary' => 'id',
                'indexes' => [
                    'uniq_payments_order'      => ['columns' => ['order_id'], 'unique' => true],
                    'uniq_payments_payment'    => ['columns' => ['payment_id'], 'unique' => true],
                    'uniq_payments_access'     => ['columns' => ['access_token'], 'unique' => true],
                    'idx_payments_status_date' => ['columns' => ['status', 'created_at'], 'unique' => false],
                    'idx_payments_email'       => ['columns' => ['user_email'], 'unique' => false],
                    'idx_payments_mobile'      => ['columns' => ['user_mobile'], 'unique' => false],
                    'idx_payments_access_end'  => ['columns' => ['access_expires_at'], 'unique' => false],
                    'idx_payments_product'     => ['columns' => ['product_type', 'product_id'], 'unique' => false],
                ],
            ],

            'payment_events' => [
                'columns' => [
                    'id'         => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'payment_id' => 'INT UNSIGNED DEFAULT NULL',
                    'event'      => 'VARCHAR(50) NOT NULL',
                    'message'    => 'VARCHAR(255) DEFAULT NULL',
                    'payload'    => 'TEXT DEFAULT NULL',
                    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                ],
                'primary' => 'id',
                'indexes' => [
                    'idx_events_payment' => ['columns' => ['payment_id', 'created_at'], 'unique' => false],
                ],
            ],

            'videos' => [
                'columns' => [
                    'id'          => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'title'       => 'VARCHAR(160) NOT NULL',
                    'description' => 'TEXT DEFAULT NULL',
                    'youtube_url' => 'VARCHAR(255) NOT NULL',
                    'price'       => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
                    'position'    => 'INT NOT NULL DEFAULT 0',
                    'is_active'   => 'TINYINT(1) NOT NULL DEFAULT 1',
                    'created_at'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                ],
                'primary' => 'id',
                'indexes' => [
                    'idx_videos_active' => ['columns' => ['is_active', 'position'], 'unique' => false],
                ],
            ],

            'bundles' => [
                'columns' => [
                    'id'          => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'name'        => 'VARCHAR(160) NOT NULL',
                    'description' => 'TEXT DEFAULT NULL',
                    'price'       => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
                    'position'    => 'INT NOT NULL DEFAULT 0',
                    'is_active'   => 'TINYINT(1) NOT NULL DEFAULT 1',
                    'created_at'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                ],
                'primary' => 'id',
                'indexes' => [
                    'idx_bundles_active' => ['columns' => ['is_active', 'position'], 'unique' => false],
                ],
            ],

            'bundle_items' => [
                'columns' => [
                    'id'         => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'bundle_id'  => 'INT UNSIGNED NOT NULL',
                    'video_id'   => 'INT UNSIGNED NOT NULL',
                    'position'   => 'INT NOT NULL DEFAULT 0',
                    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                ],
                'primary' => 'id',
                'indexes' => [
                    'uniq_bundle_video'      => ['columns' => ['bundle_id', 'video_id'], 'unique' => true],
                    'idx_bundle_items_video' => ['columns' => ['video_id'], 'unique' => false],
                ],
            ],

            // What each payment unlocks (snapshot: editing a bundle later must not
            // add or remove videos from a completed purchase).
            'payment_videos' => [
                'columns' => [
                    'id'         => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'payment_id' => 'INT UNSIGNED NOT NULL',
                    'video_id'   => 'INT UNSIGNED NOT NULL',
                    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                ],
                'primary' => 'id',
                'indexes' => [
                    'uniq_payment_video'        => ['columns' => ['payment_id', 'video_id'], 'unique' => true],
                    'idx_payment_videos_video'  => ['columns' => ['video_id'], 'unique' => false],
                ],
            ],

            'rate_events' => [
                'columns' => [
                    'id'         => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'bucket'     => 'VARCHAR(40) NOT NULL',
                    'identifier' => 'VARCHAR(190) NOT NULL',
                    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                ],
                'primary' => 'id',
                'indexes' => [
                    'idx_rate_lookup' => ['columns' => ['bucket', 'identifier', 'created_at'], 'unique' => false],
                ],
            ],
        ];
    }

    /**
     * CREATE TABLE statement for a table defined above.
     */
    public static function createTableSql($table)
    {
        $tables = self::tables();
        if (!isset($tables[$table])) {
            throw new RuntimeException('Unknown table: ' . $table);
        }
        $definition = $tables[$table];
        $lines = [];

        foreach ($definition['columns'] as $name => $sql) {
            $lines[] = '`' . $name . '` ' . $sql;
        }
        if (!empty($definition['primary'])) {
            $lines[] = 'PRIMARY KEY (`' . $definition['primary'] . '`)';
        }
        foreach ($definition['indexes'] as $name => $index) {
            $columns = '`' . implode('`, `', $index['columns']) . '`';
            $lines[] = (!empty($index['unique']) ? 'UNIQUE KEY ' : 'KEY ') . '`' . $name . '` (' . $columns . ')';
        }

        return "CREATE TABLE IF NOT EXISTS `{$table}` (\n    " . implode(",\n    ", $lines)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    /**
     * Complete SQL dump (used for sql/schema.sql and manual installs).
     */
    public static function sqlDump()
    {
        $sql = "-- =============================================================================\n"
            . "-- PayAccess — MySQL schema (MySQL 5.7+ / MariaDB 10.3+)\n"
            . "-- Generated from lib/schema.php by tools/build-schema.php — do not edit by hand.\n"
            . "--\n"
            . "-- Fresh install: `install.php` does all of this for you.\n"
            . "-- Manual install: import this file into an empty utf8mb4 database.\n"
            . "-- Existing (v1) database: open `update.php` instead — it adds what is missing\n"
            . "-- without touching your data.\n"
            . "-- =============================================================================\n\n";

        foreach (array_keys(self::tables()) as $table) {
            $sql .= self::createTableSql($table) . ";\n\n";
        }

        $sql .= "-- Default settings row (ignored when it already exists).\n"
            . "INSERT IGNORE INTO `settings`\n"
            . "    (`id`, `site_name`, `logo_text`, `tagline`, `amount`, `currency`, `access_days`, `theme_color`, `updated_at`)\n"
            . "VALUES (1, 'PayAccess', 'PayAccess', 'Get instant access to our video library', 199.00, 'INR', 0, '#4f46e5', NOW());\n";

        return $sql;
    }

    // -------------------------------------------------------------------------
    // Verification
    // -------------------------------------------------------------------------

    /**
     * Is the database schema up to date? Uses a cached marker when possible.
     */
    public static function isCurrent(DatabaseInterface $db)
    {
        if (self::markerExists()) {
            return true;
        }
        if (self::missing($db) === []) {
            self::writeMarker();
            return true;
        }
        return false;
    }

    /**
     * Human readable list of everything that is missing (empty = up to date).
     *
     * @return array<int, string>
     */
    public static function missing(DatabaseInterface $db)
    {
        $missingTables = [];
        $missingColumns = [];
        $tooStrict = [];

        foreach (self::tables() as $table => $definition) {
            try {
                $rows = self::columnRows($db, $table);
            } catch (Throwable $e) {
                $missingTables[] = 'table `' . $table . '`';
                continue;
            }

            $columns = array_keys($rows);
            $absent = array_values(array_diff(array_keys($definition['columns']), $columns));
            if ($absent !== []) {
                $missingColumns[] = 'column(s) `' . $table . '.' . implode('`, `' . $table . '.', $absent) . '`';
            }

            // A column v2 writes as NULL later on must not be NOT NULL without a
            // default, or the first INSERT fails ("cannot be null").
            foreach ($definition['columns'] as $name => $sql) {
                if (stripos((string) $sql, 'NOT NULL') !== false || !isset($rows[$name])) {
                    continue;
                }
                $row = $rows[$name];
                $extra = isset($row['Extra']) ? strtolower((string) $row['Extra']) : '';
                if (!self::isStrictColumn($row) || strpos($extra, 'auto_increment') !== false) {
                    continue;
                }
                $tooStrict[] = 'column `' . $table . '.' . $name . '` must allow NULL';
            }
        }

        // Order matters: the boot page only has room for the first few items, so
        // report whole tables first, then the constraints that block checkout.
        $missing = array_merge($missingTables, $tooStrict, $missingColumns);

        // A settings row must exist for the app to work.
        try {
            if ((int) $db->value('SELECT COUNT(*) FROM settings', [], 0) === 0) {
                $missing[] = 'the default settings row';
            }
        } catch (Throwable $e) {
            $missing[] = 'the default settings row';
        }

        return $missing;
    }

    // -------------------------------------------------------------------------
    // Migration
    // -------------------------------------------------------------------------

    /**
     * Create whatever is missing. Safe to run repeatedly.
     *
     * @param  DatabaseInterface       $db
     * @param  callable|null           $logger function (string $message): void
     * @return array<string, mixed>    report: tables_created, columns_added,
     *                                 indexes_added, columns_relaxed,
     *                                 data_changes, errors, ok
     */
    public static function migrate(DatabaseInterface $db, $logger = null)
    {
        $log = function ($message) use ($logger) {
            if (is_callable($logger)) {
                call_user_func($logger, $message);
            }
        };

        $report = [
            'ok'             => true,
            'tables_created' => [],
            'columns_added'  => [],
            'indexes_added'  => [],
            'columns_relaxed' => [],
            'data_changes'   => [],
            'errors'         => [],
        ];

        self::clearMarker();
        $log('Checking database schema…');

        $existingTables = self::existingTables($db);
        $settingsColumns = [];
        $siteNameWasAdded = false;

        foreach (self::tables() as $table => $definition) {
            if (!in_array($table, $existingTables, true)) {
                try {
                    $db->exec(self::createTableSql($table));
                    $report['tables_created'][] = $table;
                    $log('Created table `' . $table . '`.');
                } catch (Throwable $e) {
                    $report['ok'] = false;
                    $report['errors'][] = 'Could not create table `' . $table . '`: ' . $e->getMessage();
                    $log('FAILED creating table `' . $table . '`.');
                    continue;
                }
                continue;
            }

            try {
                $columns = self::existingColumns($db, $table);
            } catch (Throwable $e) {
                $report['ok'] = false;
                $report['errors'][] = 'Could not inspect table `' . $table . '`: ' . $e->getMessage();
                continue;
            }
            if ($table === 'settings') {
                $settingsColumns = $columns;
            }
            // Columns that were just added were backfilled with their DEFAULT by
            // MySQL, so remember `site_name` to copy the old brand name over it.
            if ($table === 'settings' && !in_array('site_name', $columns, true)) {
                $siteNameWasAdded = true;
            }

            foreach ($definition['columns'] as $name => $sql) {
                if (in_array($name, $columns, true)) {
                    continue;
                }
                try {
                    $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . self::alterableDefinition($sql));
                    $report['columns_added'][] = $table . '.' . $name;
                    $log('Added `' . $table . '.' . $name . '`.');
                } catch (Throwable $e) {
                    $report['ok'] = false;
                    $report['errors'][] = 'Could not add `' . $table . '.' . $name . '`: ' . $e->getMessage();
                }
            }

            // A v1 table may declare NOT NULL on a column v2 fills in later
            // (e.g. `payments.order_id`: v1 inserted the row only after Razorpay
            // accepted the order, v2 inserts it as pending first). Such a column
            // makes every checkout fail with "Column ... cannot be null".
            self::relaxNotNulls($db, $table, $definition, $report, $log);

            $indexes = [];
            try {
                $indexes = self::existingIndexes($db, $table);
            } catch (Throwable $e) {
                // Non-fatal: indexes are an optimisation.
            }
            foreach ($definition['indexes'] as $name => $index) {
                if (in_array($name, $indexes, true)) {
                    continue;
                }
                $columnsSql = '`' . implode('`, `', $index['columns']) . '`';
                try {
                    $db->exec(
                        'ALTER TABLE `' . $table . '` ADD ' . (!empty($index['unique']) ? 'UNIQUE ' : '')
                        . 'INDEX `' . $name . '` (' . $columnsSql . ')'
                    );
                    $report['indexes_added'][] = $table . '.' . $name;
                } catch (Throwable $e) {
                    // Duplicate data (or an existing equivalent index) — keep going.
                    $log('Index `' . $name . '` skipped: ' . $e->getMessage());
                }
            }
        }

        // --- Data normalisation (v1 → v2), all guarded and idempotent -------
        try {
            if ((int) $db->value('SELECT COUNT(*) FROM settings', [], 0) === 0) {
                $db->exec(
                    "INSERT INTO `settings`
                        (`id`, `site_name`, `logo_text`, `tagline`, `amount`, `currency`, `access_days`, `theme_color`, `updated_at`)
                     VALUES (1, 'PayAccess', 'PayAccess', 'Get instant access to our video library', 199.00, 'INR', 0, '#4f46e5', NOW())"
                );
                $report['data_changes'][] = 'Inserted the default settings row.';
                $log('Inserted the default settings row.');
            }
        } catch (Throwable $e) {
            $report['ok'] = false;
            $report['errors'][] = 'Could not create the settings row: ' . $e->getMessage();
        }

        // v1 had no site_name at all (only logo_text), and a freshly added
        // column is backfilled with its DEFAULT — so copy the brand name over.
        try {
            $where = $siteNameWasAdded
                ? "(site_name IS NULL OR site_name = '' OR site_name = 'PayAccess')"
                : "(site_name IS NULL OR site_name = '')";
            $affected = $db->exec(
                "UPDATE settings SET site_name = logo_text WHERE {$where} AND logo_text IS NOT NULL AND logo_text <> ''"
            );
            if ($affected > 0) {
                $report['data_changes'][] = 'Copied logo_text into site_name.';
            }
        } catch (Throwable $e) {
            // Column may not exist on exotic schemas; ignore.
        }

        // v1 called the video column youtube_link.
        if (in_array('youtube_link', $settingsColumns, true)) {
            try {
                $affected = $db->exec(
                    "UPDATE settings
                     SET youtube_video_url = youtube_link
                     WHERE (youtube_video_url IS NULL OR youtube_video_url = '')
                       AND youtube_link IS NOT NULL AND youtube_link <> ''"
                );
                if ($affected > 0) {
                    $report['data_changes'][] = 'Copied youtube_link into youtube_video_url.';
                }
            } catch (Throwable $e) {
                $log('Could not copy youtube_link: ' . $e->getMessage());
            }
        }

        // v1 stored a logo URL; keep showing it until something is uploaded.
        if (in_array('logo', $settingsColumns, true)) {
            try {
                $affected = $db->exec(
                    "UPDATE settings
                     SET logo_path = logo
                     WHERE (logo_path IS NULL OR logo_path = '')
                       AND logo IS NOT NULL AND logo <> ''"
                );
                if ($affected > 0) {
                    $report['data_changes'][] = 'Carried the old logo URL over to the uploader setting.';
                }
            } catch (Throwable $e) {
                $log('Could not copy the old logo: ' . $e->getMessage());
            }
        }

        // --- Catalog bootstrap (v2 single video → v3 library) -----------------
        // Creates one video from the old "YouTube video link" setting and gives
        // every buyer who already paid access to it, so nothing that worked
        // before this update stops working.
        try {
            $catalogReport = self::seedCatalog($db, $log);
            foreach ($catalogReport as $change) {
                $report['data_changes'][] = $change;
            }
        } catch (Throwable $e) {
            $report['errors'][] = 'Could not prepare the video library: ' . $e->getMessage();
            $log('Could not prepare the video library: ' . $e->getMessage());
        }

        if ($report['ok']) {
            self::writeMarker();
        }

        return $report;
    }

    /**
     * Turn the v2 single-video setup into the first entry of the video library.
     *
     * Runs once (only while the library is empty) and is completely additive:
     * the settings row keeps its link, existing payments keep their token and
     * gain an explicit entitlement to the video they paid for.
     *
     * @param  DatabaseInterface $db
     * @param  callable          $log
     * @return array<int, string> human readable changes for the update report
     */
    private static function seedCatalog(DatabaseInterface $db, $log)
    {
        $changes = [];

        $videoCount = (int) $db->value('SELECT COUNT(*) FROM videos', [], 0);
        if ($videoCount > 0) {
            return $changes; // The owner already has a library; never touch it.
        }

        $settings = $db->first('SELECT * FROM settings WHERE id = 1 LIMIT 1');
        if (!is_array($settings)) {
            return $changes;
        }

        $videoUrl = isset($settings['youtube_video_url']) ? trim((string) $settings['youtube_video_url']) : '';
        $videoId = null;

        if ($videoUrl !== '') {
            $title = trim((string) ($settings['site_name'] ?? ''));
            if ($title === '') {
                $title = 'Your video';
            }
            $price = isset($settings['amount']) ? (float) $settings['amount'] : 199.0;

            $videoId = (int) $db->insert('videos', [
                'title'       => db_limit($title, 160),
                'description' => 'Your first video. Edit it in Admin → Videos.',
                'youtube_url' => db_limit($videoUrl, 255),
                'price'       => number_format(max(0, $price), 2, '.', ''),
                'position'    => 1,
                'is_active'   => 1,
            ]);
            $changes[] = 'Created the first video from your existing YouTube link.';
            $log('Created the first video from the existing YouTube link.');

            // Every past buyer keeps access to the video they paid for.
            $payments = $db->all("SELECT id FROM payments WHERE status = 'success'");
            $linked = 0;
            foreach ($payments as $payment) {
                try {
                    $already = (int) $db->value('SELECT COUNT(*) FROM payment_videos WHERE payment_id = ?', [(int) $payment['id']], 0);
                    if ($already > 0) {
                        continue;
                    }
                    $db->insert('payment_videos', [
                        'payment_id' => (int) $payment['id'],
                        'video_id'   => $videoId,
                    ]);
                    $linked++;
                } catch (Throwable $e) {
                    $log('Could not link payment #' . $payment['id'] . ': ' . $e->getMessage());
                }
            }
            if ($linked > 0) {
                $changes[] = 'Gave ' . $linked . ' existing buyer(s) access to that video.';
                $log('Gave ' . $linked . ' existing buyer(s) access to the video.');
            }
        }

        // "Watch anytime" is the default from v3 on. The old default was 30 days;
        // only rewrite that exact value so a deliberate choice is preserved.
        if ((int) ($settings['access_days'] ?? 0) === 30) {
            try {
                $db->exec('UPDATE settings SET access_days = 0 WHERE id = 1');
                $changes[] = 'Access is now unlimited (was 30 days) — set a number in Settings to limit it.';
                $log('Set access to unlimited (was the old 30-day default).');
            } catch (Throwable $e) {
                $log('Could not change the access duration: ' . $e->getMessage());
            }
        }

        return $changes;
    }

    /**
     * True when the column is NOT NULL and has no default, i.e. MySQL rejects an
     * INSERT that leaves it out.
     *
     * @param array<string, mixed> $row a SHOW COLUMNS row
     */
    private static function isStrictColumn(array $row)
    {
        $null = isset($row['Null']) ? strtoupper((string) $row['Null']) : 'YES';
        $default = array_key_exists('Default', $row) ? $row['Default'] : null;
        return $null === 'NO' && $default === null;
    }

    /**
     * Allow NULL on columns that cannot hold a value yet.
     *
     * Only columns that are NOT NULL *and* have no DEFAULT are touched — those
     * are the ones that reject an INSERT. Relaxing a constraint keeps every
     * row intact and never widens a column the app relies on:
     *
     *   - columns we declare nullable in `tables()` (order_id, access_token, …)
     *   - leftover v1 columns we do not know about, as long as they have no
     *     DEFAULT (nothing sensible could be filled in anyway)
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $report
     * @param callable             $log
     */
    private static function relaxNotNulls(DatabaseInterface $db, $table, array $definition, array &$report, $log)
    {
        try {
            $rows = $db->all('SHOW COLUMNS FROM `' . self::safeName($table) . '`');
        } catch (Throwable $e) {
            return; // Cannot inspect — the INSERT/UPDATE errors will explain why.
        }

        foreach ($rows as $row) {
            if (!isset($row['Field'])) {
                continue;
            }
            $field = (string) $row['Field'];
            $extra = isset($row['Extra']) ? strtolower((string) $row['Extra']) : '';

            if (!self::isStrictColumn($row)) {
                continue; // already nullable, or MySQL fills it for us
            }
            if (strpos($extra, 'auto_increment') !== false || strpos($extra, 'on update') !== false) {
                continue;
            }
            if (isset($row['Key']) && strtoupper((string) $row['Key']) === 'PRI') {
                continue;
            }

            $ourDefinition = isset($definition['columns'][$field]) ? (string) $definition['columns'][$field] : null;
            if ($ourDefinition !== null && stripos($ourDefinition, 'NOT NULL') !== false) {
                continue; // the app always writes a value here
            }

            $type = isset($row['Type']) && $row['Type'] !== '' ? (string) $row['Type'] : null;
            if ($type === null) {
                continue;
            }

            try {
                $db->exec('ALTER TABLE `' . self::safeName($table) . '` MODIFY `' . self::safeName($field) . '` ' . $type . ' NULL');
                $report['columns_relaxed'][] = $table . '.' . $field;
                $log('Allowed NULL for `' . $table . '.' . $field . '` (it was NOT NULL without a default).');
            } catch (Throwable $e) {
                $report['errors'][] = 'Could not allow NULL for `' . $table . '.' . $field . '`: ' . $e->getMessage();
                $log('FAILED allowing NULL for `' . $table . '.' . $field . '`.');
            }
        }
    }

    /**
     * Turn a column definition into something safe to ADD to a populated table
     * (no AUTO_INCREMENT, no NOT NULL without a default).
     */
    private static function alterableDefinition($sql)
    {
        $definition = preg_replace('/\s+AUTO_INCREMENT/i', '', (string) $sql);
        if (stripos($definition, 'NOT NULL') !== false && stripos($definition, 'DEFAULT') === false) {
            $definition = str_ireplace('NOT NULL', 'DEFAULT NULL', $definition);
        }
        return trim($definition);
    }

    // -------------------------------------------------------------------------
    // Introspection helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public static function existingTables(DatabaseInterface $db)
    {
        $rows = $db->all('SHOW TABLES');
        $tables = [];
        foreach ($rows as $row) {
            $values = array_values($row);
            if (isset($values[0])) {
                $tables[] = (string) $values[0];
            }
        }
        return $tables;
    }

    /**
     * @return array<int, string>
     */
    public static function existingColumns(DatabaseInterface $db, $table)
    {
        return array_keys(self::columnRows($db, $table));
    }

    /**
     * SHOW COLUMNS rows keyed by field name (Field/Type/Null/Key/Default/Extra).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function columnRows(DatabaseInterface $db, $table)
    {
        $rows = [];
        foreach ($db->all('SHOW COLUMNS FROM `' . self::safeName($table) . '`') as $row) {
            if (isset($row['Field'])) {
                $rows[(string) $row['Field']] = $row;
            }
        }
        return $rows;
    }

    /**
     * @return array<int, string>
     */
    public static function existingIndexes(DatabaseInterface $db, $table)
    {
        $rows = $db->all('SHOW INDEX FROM `' . self::safeName($table) . '`');
        $indexes = [];
        foreach ($rows as $row) {
            if (isset($row['Key_name']) && (string) $row['Key_name'] !== 'PRIMARY') {
                $indexes[] = (string) $row['Key_name'];
            }
        }
        return array_values(array_unique($indexes));
    }

    /**
     * @return string
     */
    private static function safeName($name)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $name)) {
            throw new RuntimeException('Invalid table name.');
        }
        return (string) $name;
    }

    // -------------------------------------------------------------------------
    // Cached "schema is fine" marker (avoids SHOW COLUMNS on every request)
    // -------------------------------------------------------------------------

    private static function markerPath()
    {
        $database = defined('DB_NAME') ? (string) DB_NAME : 'db';
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        return $root . '/storage/schema-' . self::VERSION . '-' . substr(md5($database), 0, 8) . '.ok';
    }

    private static function markerExists()
    {
        return is_file(self::markerPath());
    }

    private static function writeMarker()
    {
        $path = self::markerPath();
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            return; // No cache — the check simply runs again next time.
        }
        @file_put_contents($path, date('c') . PHP_EOL);
    }

    /**
     * Forget the cached check (called after migrating or saving schema changes).
     */
    public static function clearMarker()
    {
        $path = self::markerPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
