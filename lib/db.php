<?php
/**
 * Thin, safe wrapper around PDO (MySQL) used across the app.
 */

interface DatabaseInterface
{
    /**
     * Run a query and return the statement.
     *
     * @param  array<int|string, mixed> $params
     * @return PDOStatement|mixed
     */
    public function query($sql, array $params = []);

    /**
     * Fetch every row.
     *
     * @param  array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function all($sql, array $params = []);

    /**
     * Fetch a single row or null.
     *
     * @param  array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function first($sql, array $params = []);

    /**
     * Fetch a single scalar value.
     *
     * @param  array<int|string, mixed> $params
     * @param  mixed                    $default
     * @return mixed
     */
    public function value($sql, array $params = [], $default = null);

    /**
     * Execute a statement and return the affected row count.
     *
     * @param  array<int|string, mixed> $params
     * @return int
     */
    public function exec($sql, array $params = []);

    /**
     * Insert a row and return its id.
     *
     * @param array<string, mixed> $data
     * @return int
     */
    public function insert($table, array $data);

    /**
     * Update rows and return the affected row count.
     *
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $params
     * @return int
     */
    public function update($table, array $data, $where, array $params = []);

    /**
     * @return string
     */
    public function lastInsertId();

    /**
     * Run a callback inside a transaction.
     *
     * @param  callable $callback
     * @return mixed
     */
    public function transaction($callback);

    /**
     * @return string
     */
    public function driver();

    /**
     * @return PDO|null
     */
    public function pdo();
}

class DatabaseException extends RuntimeException
{
    /** @var string SQLSTATE such as 23000 or HY093 ('' when unknown) */
    public $sqlState = '';

    /** @var int MySQL driver error number (0 when unknown) */
    public $driverCode = 0;

    /** @var string the statement that failed, for the log */
    public $statement = '';

    /** @var string what the app was trying to do ("insert into `payments`") */
    public $operation = '';

    public function __construct($message = '', $code = 0, $previous = null, $sqlState = '', $driverCode = 0, $statement = '', $operation = '')
    {
        parent::__construct($message, (int) $code, $previous instanceof Throwable ? $previous : null);
        $this->sqlState = (string) $sqlState;
        $this->driverCode = (int) $driverCode;
        $this->statement = (string) $statement;
        $this->operation = (string) $operation;
    }
}

class Database implements DatabaseInterface
{
    /** @var PDO */
    private $pdo;

    /**
     * @param array<int, mixed> $options
     */
    public function __construct($dsn, $username = null, $password = null, array $options = [])
    {
        $defaults = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ];
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $defaults[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options + $defaults);
        } catch (PDOException $e) {
            throw new DatabaseException($e->getMessage(), (int) $e->getCode());
        }
    }

    /**
     * Connect using the DB_* constants from config.php.
     */
    public static function fromConfig()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            defined('DB_PORT') ? DB_PORT : '3306',
            DB_NAME
        );
        return new self($dsn, DB_USER, DB_PASS);
    }

    public function pdo()
    {
        return $this->pdo;
    }

    public function driver()
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function query($sql, array $params = [])
    {
        try {
            $statement = $this->pdo->prepare((string) $sql);
            $statement->execute($params);
            return $statement;
        } catch (PDOException $e) {
            $this->fail($sql, $e);
        }
    }

    public function all($sql, array $params = [])
    {
        $statement = $this->query($sql, $params);
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function first($sql, array $params = [])
    {
        $statement = $this->query($sql, $params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function value($sql, array $params = [], $default = null)
    {
        $statement = $this->query($sql, $params);
        $value = $statement->fetchColumn();
        return $value === false ? $default : $value;
    }

    public function exec($sql, array $params = [])
    {
        $statement = $this->query($sql, $params);
        return $statement->rowCount();
    }

    public function insert($table, array $data)
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot insert an empty row.');
        }
        $columns = array_keys($data);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->identifier($table),
            implode('`, `', array_map([$this, 'identifier'], $columns)),
            implode(', ', array_fill(0, count($columns), '?'))
        );

        $this->query($sql, array_values($data));
        return (int) $this->lastInsertId();
    }

    public function update($table, array $data, $where, array $params = [])
    {
        if (empty($data)) {
            return 0;
        }

        // Every placeholder must use the same style: PDO rejects a statement that
        // mixes named and positional ones with "SQLSTATE[HY093]: Invalid parameter
        // number". Callers pass `WHERE id = ?`, so the SET list is positional too.
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = '`' . $this->identifier($column) . '` = ?';
        }

        list($whereSql, $whereValues) = $this->normaliseWhere($where, $params);

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->identifier($table),
            implode(', ', $sets),
            $whereSql
        );

        return $this->exec($sql, array_merge(array_values($data), $whereValues));
    }

    /**
     * Rewrite named placeholders in a WHERE clause as positional ones, in the
     * order they appear, collecting their values. A plain `id = ?` passes
     * through untouched.
     *
     * @param  string             $where
     * @param  array<string,mixed> $params
     * @return array{0:string,1:array<int,mixed>}
     */
    private function normaliseWhere($where, array $params)
    {
        $where = (string) $where;

        if (!preg_match('/:[A-Za-z_][A-Za-z0-9_]*/', $where)) {
            return [$where, array_values($params)];
        }

        $values = [];
        $sql = preg_replace_callback('/:([A-Za-z_][A-Za-z0-9_]*)/', function ($match) use ($params, &$values) {
            $name = $match[1];
            if (array_key_exists(':' . $name, $params)) {
                $values[] = $params[':' . $name];
            } elseif (array_key_exists($name, $params)) {
                $values[] = $params[$name];
            } else {
                throw new DatabaseException('Missing value for the `:' . $name . '` placeholder.');
            }
            return '?';
        }, $where);

        return [$sql, $values];
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function transaction($callback)
    {
        $this->pdo->beginTransaction();
        try {
            $result = call_user_func($callback, $this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Escape/validate an SQL identifier (table or column name).
     */
    public function identifier($name)
    {
        $name = (string) $name;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new DatabaseException('Invalid SQL identifier.');
        }
        return $name;
    }

    /**
     * @param string $sql
     * @return void
     */
    private function fail($sql, PDOException $e)
    {
        $statement = preg_replace('/\s+/', ' ', (string) $sql);
        $state = '';
        $driverCode = 0;

        if (is_array($e->errorInfo)) {
            $state = isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : '';
            $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
        }
        if ($state === '' && preg_match('/SQLSTATE\[([0-9A-Z]{5})\]/', $e->getMessage(), $m)) {
            $state = $m[1];
        }
        if ($driverCode === 0 && preg_match('/SQLSTATE\[[0-9A-Z]{5}\]\s*[^:]*:\s*(\d{3,5})\b/', $e->getMessage(), $m)) {
            $driverCode = (int) $m[1];
        }

        app_log('error', 'Database error: ' . $e->getMessage(), [
            'sql'        => $statement,
            'sqlstate'   => $state,
            'code'       => $driverCode,
            'pdo_code'   => $e->getCode(),
        ]);

        throw new DatabaseException(
            $e->getMessage(),
            (int) $e->getCode(),
            $e,
            $state,
            $driverCode,
            $statement,
            self::describe($statement)
        );
    }

    /**
     * Short human description of a statement ("insert into `payments`").
     */
    private static function describe($sql)
    {
        if (preg_match('/^\s*(INSERT INTO|UPDATE|DELETE FROM|SELECT|ALTER TABLE|CREATE TABLE)\s+`?(\w+)`?/i', (string) $sql, $m)) {
            return strtolower($m[1]) . ' `' . $m[2] . '`';
        }
        return 'query';
    }
}
