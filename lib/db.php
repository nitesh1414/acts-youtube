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
        $placeholders = array_map(function ($column) {
            return ':' . $column;
        }, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->identifier($table),
            implode('`, `', array_map([$this, 'identifier'], $columns)),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function update($table, array $data, $where, array $params = [])
    {
        if (empty($data)) {
            return 0;
        }
        $sets = [];
        $bind = [];
        foreach ($data as $column => $value) {
            $sets[] = '`' . $this->identifier($column) . '` = :set_' . $column;
            $bind[':set_' . $column] = $value;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->identifier($table),
            implode(', ', $sets),
            $where
        );
        return $this->exec($sql, $bind + $params);
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
        app_log('error', 'Database error: ' . $e->getMessage(), [
            'sql'   => preg_replace('/\s+/', ' ', (string) $sql),
            'code'  => $e->getCode(),
        ]);
        throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
    }
}
