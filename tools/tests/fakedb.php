<?php
/**
 * Test double for DatabaseInterface: an in-memory store plus a tiny SQL
 * evaluator that understands exactly the statements this application issues.
 *
 * Loaded only by the Node/PHP harness (tools/tests/harness.mjs). Never used by
 * the running site.
 */

final class FakeDatabase implements DatabaseInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    public $tables = [];

    /** @var array<string, array<string, array<string, mixed>>> SHOW COLUMNS rows */
    public $schema = [];

    /** @var array<int, string> every statement executed, for assertions */
    public $log = [];

    /** @var int */
    private $lastId = 0;

    /** @var FakeDatabase|null */
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
        return self::instance();
    }

    /**
     * Create every v3 table plus the default settings row.
     *
     * @param bool $withColumns false simulates an empty database (no tables).
     */
    public function install($withColumns = true)
    {
        $this->tables = [];
        $this->schema = [];
        $this->lastId = 0;

        if (!$withColumns) {
            return $this;
        }

        foreach (Schema::tables() as $table => $definition) {
            $this->tables[$table] = [];
            $this->schema[$table] = [];
            foreach ($definition['columns'] as $column => $sql) {
                $this->schema[$table][$column] = [
                    'Field'   => $column,
                    'Type'    => 'varchar(255)',
                    'Null'    => stripos((string) $sql, 'NOT NULL') !== false ? 'NO' : 'YES',
                    'Key'     => '',
                    'Default' => $this->defaultValue((string) $sql),
                    'Extra'   => stripos((string) $sql, 'AUTO_INCREMENT') !== false ? 'auto_increment' : '',
                ];
            }
        }

        $this->tables['settings'][] = Settings::DEFAULTS + ['id' => 1, 'updated_at' => now()];
        return $this;
    }

    public function dropTable($table)
    {
        unset($this->tables[$table], $this->schema[$table]);
        return $this;
    }

    /** @param array<string, mixed> $row */
    public function add($table, array $row)
    {
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }
        if (!isset($row['id'])) {
            $row['id'] = $this->nextId($table);
        }
        $this->tables[$table][] = $row;
        return (int) $row['id'];
    }

    private function defaultValue($definition)
    {
        if (!preg_match('/\bDEFAULT\s+(?:\'([^\']*)\'|([^\s,]+))/i', (string) $definition, $match)) {
            return null;
        }
        $value = isset($match[1]) && $match[1] !== '' ? $match[1] : ($match[2] ?? '');
        if (strtoupper($value) === 'NULL') {
            return null;
        }
        return is_numeric($value) ? $value + 0 : $value;
    }

    private function nextId($table)
    {
        $max = 0;
        foreach ($this->tables[$table] ?? [] as $row) {
            if (isset($row['id']) && (int) $row['id'] > $max) {
                $max = (int) $row['id'];
            }
        }
        return $max + 1;
    }

    // -------------------------------------------------------------------------
    // DatabaseInterface
    // -------------------------------------------------------------------------

    public function query($sql, array $params = [])
    {
        $sql = trim((string) $sql);
        $this->log[] = $sql;

        // PDO rejects mixed placeholder styles with SQLSTATE HY093.
        if (preg_match('/:[a-zA-Z_]/', $sql) && strpos($sql, '?') !== false) {
            throw new DatabaseException(
                'Failed to prepare statement: mixed named and positional parameters',
                0,
                null,
                'HY093',
                0,
                $sql
            );
        }

        $this->execRaw($sql, $params);
        return new FakeStatement();
    }

    public function all($sql, array $params = [])
    {
        return $this->selectRows($this->remember($sql), $params);
    }

    public function first($sql, array $params = [])
    {
        $rows = $this->all($sql, $params);
        return $rows === [] ? null : $rows[0];
    }

    public function value($sql, array $params = [], $default = null)
    {
        $rows = $this->all($sql, $params);
        if ($rows === [] || $rows[0] === []) {
            return $default;
        }
        $first = reset($rows[0]);
        return $first === null ? $default : $first;
    }

    public function exec($sql, array $params = [])
    {
        $sql = trim((string) $sql);
        $this->log[] = $sql;

        if (stripos($sql, 'DELETE FROM') === 0) {
            return $this->deleteRows($sql, $params);
        }
        if (stripos($sql, 'UPDATE') === 0) {
            return $this->updateRows($sql, $params);
        }

        $this->execRaw($sql, $params);
        return 0;
    }

    public function insert($table, array $data)
    {
        $this->lastId = $this->add($table, $data);
        return $this->lastId;
    }

    public function update($table, array $data, $where, array $params = [])
    {
        $count = 0;
        foreach ($this->tables[$table] ?? [] as $index => $row) {
            $probe = $params; // placeholders are consumed per row
            if ($where === '' || $this->match($row, $where, $probe)) {
                $this->tables[$table][$index] = array_merge($row, $data);
                $count++;
            }
        }
        return $count;
    }

    public function lastInsertId()
    {
        return $this->lastId;
    }

    public function transaction($callback)
    {
        return call_user_func($callback, $this);
    }

    public function driver()
    {
        return 'fake';
    }

    public function pdo()
    {
        return null;
    }

    private function remember($sql)
    {
        $sql = trim(preg_replace('/\s+/', ' ', (string) $sql));
        $this->log[] = $sql;
        return $sql;
    }

    // -------------------------------------------------------------------------
    // DDL
    // -------------------------------------------------------------------------

    private function execRaw($sql, array $params)
    {
        if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $sql, $m)) {
            $name = $m[1];
            if (!isset($this->tables[$name])) {
                $this->tables[$name] = [];
            }
            $this->schema[$name] = [];
            if (class_exists('Schema') && isset(Schema::tables()[$name]['columns'])) {
                foreach (Schema::tables()[$name]['columns'] as $column => $definition) {
                    $this->schema[$name][$column] = [
                        'Field' => $column, 'Type' => 'varchar(255)',
                        'Null' => stripos((string) $definition, 'NOT NULL') !== false ? 'NO' : 'YES',
                        'Key' => '', 'Default' => $this->defaultValue((string) $definition),
                        'Extra' => stripos((string) $definition, 'AUTO_INCREMENT') !== false ? 'auto_increment' : '',
                    ];
                }
            }
            return;
        }

        if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+COLUMN\s+`?(\w+)`?\s+(.*)$/is', $sql, $m)) {
            $name = $m[1];
            $column = $m[2];
            if (isset($this->schema[$name])) {
                $this->schema[$name][$column] = [
                    'Field' => $column, 'Type' => 'varchar(255)',
                    'Null' => stripos($m[3], 'NOT NULL') !== false ? 'NO' : 'YES',
                    'Key' => '', 'Default' => $this->defaultValue($m[3]),
                    'Extra' => stripos($m[3], 'AUTO_INCREMENT') !== false ? 'auto_increment' : '',
                ];
            }
            $default = $this->defaultValue($m[3]);
            foreach ($this->tables[$name] ?? [] as $index => $row) {
                if (!array_key_exists($column, $row)) {
                    // MySQL backfills an added column with its default; model
                    // that for migration tests (especially default-on flags).
                    $this->tables[$name][$index][$column] = $default;
                }
            }
            return;
        }

        if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+MODIFY\s+(?:COLUMN\s+)?`?(\w+)`?\s+(.*)$/is', $sql, $m)) {
            if (isset($this->schema[$m[1]][$m[2]])) {
                $this->schema[$m[1]][$m[2]]['Null'] = stripos($m[3], 'NOT NULL') !== false ? 'NO' : 'YES';
            }
            return;
        }

        if (preg_match('/^(CREATE|ALTER|DROP|TRUNCATE|SET)\b/i', $sql)) {
            return;
        }

        if (preg_match('/^INSERT INTO\s+`?(\w+)`?\s*\(([^)]*)\)\s*VALUES\s*\((.*)\)/is', $sql, $m)) {
            $columns = array_map(function ($col) {
                return trim($col, " `");
            }, explode(',', $m[2]));
            $row = [];
            $position = 0;
            $positional = $params !== [] && stripos($m[3], '?') !== false;
            foreach ($columns as $column) {
                if ($positional) {
                    $row[$column] = $params[$position] ?? null;
                    $position++;
                } else {
                    $row[$column] = null; // literal INSERT from a migration
                }
            }
            $this->add($m[1], $row);
            return;
        }

        if (stripos($sql, 'INSERT INTO') === 0) {
            return; // INSERT ... SELECT
        }
        if (stripos($sql, 'UPDATE') === 0) {
            $this->updateRows($sql, $params);
            return;
        }
        if (stripos($sql, 'DELETE FROM') === 0) {
            $this->deleteRows($sql, $params);
            return;
        }
        if (stripos($sql, 'SELECT') === 0) {
            return;
        }

        throw new DatabaseException('FakeDatabase cannot run: ' . $sql, 0, null, '42000', 1064, $sql);
    }

    // -------------------------------------------------------------------------
    // SELECT
    // -------------------------------------------------------------------------

    private function selectRows($sql, array $params)
    {
        if (stripos($sql, 'SHOW COLUMNS FROM') === 0) {
            if (!preg_match('/SHOW COLUMNS FROM\s+`?(\w+)`?/i', $sql, $m)) {
                return [];
            }
            if (!isset($this->schema[$m[1]])) {
                throw new DatabaseException("Table '" . $m[1] . "' doesn't exist", 0, null, '42S02', 1146, $sql);
            }
            return array_values($this->schema[$m[1]]);
        }

        if (stripos($sql, 'SHOW INDEX FROM') === 0) {
            if (!preg_match('/SHOW INDEX FROM\s+`?(\w+)`?/i', $sql, $m)) {
                return [];
            }
            if (!isset($this->schema[$m[1]])) {
                throw new DatabaseException("Table '" . $m[1] . "' doesn't exist", 0, null, '42S02', 1146, $sql);
            }
            return [];
        }

        if (preg_match('/^SHOW TABLES$/i', $sql)) {
            $rows = [];
            foreach (array_keys($this->tables) as $name) {
                $rows[] = ['Tables_in_fake' => $name];
            }
            return $rows;
        }

        if (preg_match('/^SELECT VERSION\(\)/i', $sql)) {
            return [['VERSION()' => '8.0.36']];
        }

        if (strpos($sql, 'AS active_access') !== false) {
            return [$this->aggregateStats($params)];
        }

        if (strpos($sql, 'AS success_count') !== false) {
            return [$this->aggregateTotals($sql, $params)];
        }

        if (stripos($sql, 'GROUP BY DATE(paid_at)') !== false) {
            return $this->groupedRevenue($sql, $params);
        }

        if (!preg_match('/^SELECT\s+(.+?)\s+FROM\s+`?(\w+)`?(.*)$/is', $sql, $m)) {
            throw new DatabaseException('FakeDatabase cannot parse: ' . $sql, 0, null, '42000', 1064, $sql);
        }

        $columns = trim($m[1]);
        $table = $m[2];
        $rest = $m[3];
        $rows = $this->tables[$table] ?? [];

        $where = '';
        $order = '';
        $limit = null;
        $offset = 0;

        if (preg_match('/\bWHERE\b(.*?)(?=\bORDER BY\b|\bGROUP BY\b|\bLIMIT\b|$)/is', $rest, $wm)) {
            $where = trim($wm[1]);
        }
        if (preg_match('/\bORDER BY\b(.*?)(?=\bLIMIT\b|$)/is', $rest, $om)) {
            $order = trim($om[1]);
        }
        if (preg_match('/\bLIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?/i', $rest, $lm)) {
            $limit = (int) $lm[1];
            $offset = isset($lm[2]) ? (int) $lm[2] : 0;
        }

        $matches = [];
        foreach ($rows as $row) {
            $probe = $params; // every row consumes the placeholders independently
            if ($where === '' || $this->match($row, $where, $probe)) {
                $matches[] = $row;
            }
        }

        if ($order !== '') {
            $matches = $this->sortRows($matches, $order);
        }
        if ($limit !== null) {
            $matches = array_slice($matches, $offset, $limit);
        }

        if ($columns === '*') {
            return $matches;
        }

        if (preg_match('/^COUNT\(\*\)/i', $columns)) {
            return [['COUNT(*)' => count($matches), 'total' => count($matches)]];
        }

        if (preg_match('/^COUNT\(DISTINCT\s+(\w+)\)/i', $columns, $cm)) {
            $seen = [];
            foreach ($matches as $row) {
                $seen[(string) ($row[$cm[1]] ?? '')] = true;
            }
            return [['COUNT(DISTINCT ' . $cm[1] . ')' => count($seen)]];
        }

        $wanted = [];
        foreach (explode(',', $columns) as $part) {
            $part = trim($part);
            if (preg_match('/^(\w+)(?:\s+AS\s+(\w+))?$/i', $part, $pm)) {
                $wanted[] = ['column' => $pm[1], 'alias' => isset($pm[2]) ? $pm[2] : $pm[1]];
            } else {
                $wanted[] = ['column' => $part, 'alias' => $part];
            }
        }

        $out = [];
        foreach ($matches as $row) {
            $projected = [];
            foreach ($wanted as $spec) {
                $projected[$spec['alias']] = $row[$spec['column']] ?? null;
            }
            $out[] = $projected;
        }
        return $out;
    }

    private function aggregateStats(array $params)
    {
        $rows = $this->tables['payments'] ?? [];
        $todayStart = (string) ($params[0] ?? '');
        $weekStart = (string) ($params[1] ?? '');
        $monthStart = (string) ($params[2] ?? '');
        $now = (string) ($params[3] ?? '');

        $stats = [
            'total' => count($rows), 'success_count' => 0, 'pending_count' => 0,
            'failed_count' => 0, 'revenue' => 0.0, 'revenue_today' => 0.0,
            'revenue_week' => 0.0, 'revenue_month' => 0.0, 'customers' => 0,
            'active_access' => 0,
        ];
        $mobiles = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $amount = (float) ($row['amount'] ?? 0);
            $paidAt = (string) ($row['paid_at'] ?? '');
            if ($status === 'pending') {
                $stats['pending_count']++;
            }
            if ($status === 'failed') {
                $stats['failed_count']++;
            }
            if ($status !== 'success') {
                continue;
            }
            $stats['success_count']++;
            $stats['revenue'] += $amount;
            $mobiles[(string) ($row['user_mobile'] ?? '')] = true;
            if ($paidAt !== '' && $paidAt >= $todayStart) {
                $stats['revenue_today'] += $amount;
            }
            if ($paidAt !== '' && $paidAt >= $weekStart) {
                $stats['revenue_week'] += $amount;
            }
            if ($paidAt !== '' && $paidAt >= $monthStart) {
                $stats['revenue_month'] += $amount;
            }
            $expires = $row['access_expires_at'] ?? null;
            if ($expires === null || $expires === '' || (string) $expires > $now) {
                $stats['active_access']++;
            }
        }
        $stats['customers'] = count(array_filter(array_keys($mobiles), function ($mobile) {
            return $mobile !== '';
        }));
        return $stats;
    }

    private function aggregateTotals($sql, array $params)
    {
        $rows = $this->filterRows('payments', $this->whereFromStatement($sql), $params);
        $success = 0;
        $pending = 0;
        $revenue = 0.0;
        foreach ($rows as $row) {
            if (($row['status'] ?? '') === 'success') {
                $success++;
                $revenue += (float) ($row['amount'] ?? 0);
            } elseif (($row['status'] ?? '') === 'pending') {
                $pending++;
            }
        }
        return ['success_count' => $success, 'pending_count' => $pending, 'revenue' => $revenue];
    }

    private function groupedRevenue($sql, array $params)
    {
        $rows = $this->filterRows('payments', $this->whereFromStatement($sql), $params);
        $byDay = [];
        foreach ($rows as $row) {
            $day = substr((string) ($row['paid_at'] ?? ''), 0, 10);
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['day' => $day, 'payments' => 0, 'total' => 0.0];
            }
            $byDay[$day]['payments']++;
            $byDay[$day]['total'] += (float) ($row['amount'] ?? 0);
        }
        ksort($byDay);
        return array_values($byDay);
    }

    private function whereFromStatement($sql)
    {
        if (preg_match('/\bWHERE\b(.*)$/is', $sql, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function filterRows($table, $where, array $params)
    {
        $out = [];
        foreach ($this->tables[$table] ?? [] as $row) {
            $probe = $params;
            if ($where === '' || $this->match($row, $where, $probe)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    // -------------------------------------------------------------------------
    // WHERE
    // -------------------------------------------------------------------------

    private function match($row, $where, array &$params)
    {
        $where = trim((string) $where);
        if ($where === '') {
            return true;
        }

        $parts = [];
        $depth = 0;
        $current = '';
        $length = strlen($where);
        for ($i = 0; $i < $length; $i++) {
            $char = $where[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            if ($depth === 0 && strcasecmp(substr($where, $i, 5), ' AND ') === 0) {
                $parts[] = trim($current);
                $current = '';
                $i += 4;
                continue;
            }
            $current .= $char;
        }
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        foreach ($parts as $part) {
            if (!$this->matchOne($row, $part, $params)) {
                return false;
            }
        }
        return true;
    }

    private function matchOne($row, $part, array &$params)
    {
        $part = trim($part);

        if ($part !== '' && $part[0] === '(' && substr($part, -1) === ')') {
            $inner = trim(substr($part, 1, -1));
            foreach (preg_split('/\s+OR\s+/i', $inner) as $orPart) {
                $probe = $params;
                if ($this->matchOne($row, $orPart, $probe)) {
                    return true;
                }
            }
            return false;
        }

        if (preg_match('/^(\w+)\s+IS\s+NULL$/i', $part, $m)) {
            return ($row[$m[1]] ?? null) === null;
        }
        if (preg_match('/^(\w+)\s+IS\s+NOT\s+NULL$/i', $part, $m)) {
            return ($row[$m[1]] ?? null) !== null;
        }
        if (preg_match('/^(\w+)\s+LIKE\s+\?$/i', $part, $m)) {
            $pattern = array_shift($params);
            $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote((string) $pattern, '/')) . '$/i';
            return (bool) preg_match($regex, (string) ($row[$m[1]] ?? ''));
        }
        if (preg_match('/^(\w+)\s+NOT\s+LIKE\s+\?$/i', $part, $m)) {
            $pattern = array_shift($params);
            $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote((string) $pattern, '/')) . '$/i';
            return !preg_match($regex, (string) ($row[$m[1]] ?? ''));
        }
        if (preg_match('/^(\w+)\s+IN\s*\((.*)\)$/is', $part, $m)) {
            $inner = trim($m[2]);
            if ($inner === '') {
                return false;
            }
            $values = [];
            foreach (explode(',', $inner) as $token) {
                $token = trim($token);
                $values[] = $token === '?' ? array_shift($params) : trim($token, "'\"");
            }
            foreach ($values as $value) {
                if ((string) ($row[$m[1]] ?? null) === (string) $value) {
                    return true;
                }
            }
            return false;
        }
        if (preg_match('/^DATE\((\w+)\)\s*=\s*\?$/i', $part, $m)) {
            $value = array_shift($params);
            return substr((string) ($row[$m[1]] ?? ''), 0, 10) === (string) $value;
        }
        if (preg_match('/^(\w+)\s*=\s*NOW\(\)$/i', $part)) {
            return true;
        }

        if (preg_match('/^(\w+)\s*(>=|<=|<>|!=|=|>|<)\s*(.+)$/i', $part, $m)) {
            $column = $m[1];
            $operator = $m[2];
            $operand = trim($m[3]);

            if ($operand === '?') {
                $value = array_shift($params);
            } elseif (strcasecmp($operand, 'NULL') === 0) {
                $value = null;
            } else {
                $value = trim($operand, "'\"");
            }

            $actual = $row[$column] ?? null;

            switch ($operator) {
                case '=':
                    if ($value === null) {
                        return $actual === null;
                    }
                    return (string) $actual === (string) $value;
                case '!=':
                case '<>':
                    return (string) $actual !== (string) $value;
                case '>':
                    return $actual !== null && (string) $actual > (string) $value;
                case '<':
                    return $actual !== null && (string) $actual < (string) $value;
                case '>=':
                    return $actual !== null && (string) $actual >= (string) $value;
                case '<=':
                    return $actual !== null && (string) $actual <= (string) $value;
            }
        }

        throw new DatabaseException('FakeDatabase cannot evaluate WHERE: ' . $part);
    }

    private function sortRows(array $rows, $order)
    {
        $specs = [];
        foreach (explode(',', $order) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $descending = (bool) preg_match('/\s+DESC$/i', $chunk);
            $column = trim(preg_replace('/\s+(ASC|DESC)$/i', '', $chunk));
            $specs[] = ['column' => $column, 'desc' => $descending];
        }

        usort($rows, function ($a, $b) use ($specs) {
            foreach ($specs as $spec) {
                $column = $spec['column'];
                if (!array_key_exists($column, $a) && $column === 'day') {
                    $column = 'paid_at';
                }
                $left = $a[$column] ?? null;
                $right = $b[$column] ?? null;
                if (is_numeric($left) && is_numeric($right)) {
                    $cmp = (float) $left <=> (float) $right;
                } else {
                    $cmp = strcmp((string) $left, (string) $right);
                }
                if ($cmp !== 0) {
                    return $spec['desc'] ? -$cmp : $cmp;
                }
            }
            return 0;
        });

        return $rows;
    }

    // -------------------------------------------------------------------------
    // UPDATE / DELETE (raw SQL)
    // -------------------------------------------------------------------------

    private function updateRows($sql, array $params)
    {
        if (!preg_match('/^UPDATE\s+`?(\w+)`?\s+SET\s+(.*?)(?:\s+WHERE\s+(.*))?$/is', $sql, $m)) {
            return 0;
        }
        $table = $m[1];
        $assignments = $m[2];
        $where = isset($m[3]) ? trim($m[3]) : '';

        $sets = [];
        $setParams = [];
        foreach (explode(',', $assignments) as $assignment) {
            if (!preg_match('/^\s*`?(\w+)`?\s*=\s*(.+)$/s', trim($assignment), $am)) {
                continue;
            }
            $value = trim($am[2]);
            if ($value === '?') {
                $sets[$am[1]] = null;
                $setParams[] = $am[1];
            } elseif (strcasecmp($value, 'NULL') === 0) {
                $sets[$am[1]] = null;
            } elseif (preg_match("/^'(.*)'$/s", $value, $vm)) {
                $sets[$am[1]] = $vm[1];
            } elseif (is_numeric($value)) {
                $sets[$am[1]] = $value + 0;
            } else {
                $sets[$am[1]] = null;
            }
        }

        $positional = array_values(array_filter($params, 'is_int', ARRAY_FILTER_USE_KEY));
        $whereValues = array_slice($positional, count($setParams));
        foreach ($setParams as $index => $column) {
            $sets[$column] = $positional[$index] ?? null;
        }

        $count = 0;
        foreach ($this->tables[$table] ?? [] as $index => $row) {
            $probe = $whereValues;
            if ($where === '' || $this->match($row, $where, $probe)) {
                $this->tables[$table][$index] = array_merge($row, $sets);
                $count++;
            }
        }
        return $count;
    }

    private function deleteRows($sql, array $params)
    {
        if (!preg_match('/^DELETE FROM\s+`?(\w+)`?(?:\s+WHERE\s+(.*))?$/is', $sql, $m)) {
            return 0;
        }
        $table = $m[1];
        $where = isset($m[2]) ? trim($m[2]) : '';

        $keep = [];
        $count = 0;
        foreach ($this->tables[$table] ?? [] as $row) {
            $probe = $params;
            if ($where !== '' && !$this->match($row, $where, $probe)) {
                $keep[] = $row;
            } else {
                $count++;
            }
        }
        $this->tables[$table] = $keep;
        return $count;
    }
}

final class FakeStatement
{
    public function fetch()
    {
        return false;
    }

    public function fetchAll()
    {
        return [];
    }

    public function rowCount()
    {
        return 0;
    }
}

function fake_db()
{
    return FakeDatabase::instance();
}
