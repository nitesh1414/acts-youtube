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
    const VERSION = 2;

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
                    'access_days'             => 'SMALLINT UNSIGNED NOT NULL DEFAULT 30',
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
            . "VALUES (1, 'PayAccess', 'PayAccess', 'Get instant access to our exclusive video', 199.00, 'INR', 30, '#4f46e5', NOW());\n";

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

        foreach (self::tables() as $table => $definition) {
            try {
                $columns = self::existingColumns($db, $table);
            } catch (Throwable $e) {
                $missingTables[] = 'table `' . $table . '`';
                continue;
            }

            $absent = array_values(array_diff(array_keys($definition['columns']), $columns));
            if ($absent !== []) {
                $missingColumns[] = 'column(s) `' . $table . '.' . implode('`, `' . $table . '.', $absent) . '`';
            }
        }

        // Whole tables are the bigger problem (they hide the columns inside), so
        // report them first — the boot page only has room for the first few items.
        $missing = array_merge($missingTables, $missingColumns);

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
     *                                 indexes_added, data_changes, errors, ok
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
                     VALUES (1, 'PayAccess', 'PayAccess', 'Get instant access to our exclusive video', 199.00, 'INR', 30, '#4f46e5', NOW())"
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

        if ($report['ok']) {
            self::writeMarker();
        }

        return $report;
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
        $rows = $db->all('SHOW COLUMNS FROM `' . self::safeName($table) . '`');
        $columns = [];
        foreach ($rows as $row) {
            if (isset($row['Field'])) {
                $columns[] = (string) $row['Field'];
            }
        }
        return $columns;
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
