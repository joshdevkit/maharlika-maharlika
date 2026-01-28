<?php

namespace Maharlika\Database;

use Maharlika\Contracts\Database\ConnectionInterface;
use Maharlika\Database\Query\QueryLog;
use Maharlika\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

class Connection implements ConnectionInterface
{
    protected ?PDO $pdo = null;
    protected int $transactionLevel = 0;
    protected ?string $name = null;
    protected bool $connected = false;
    protected ?DatabaseException $connectionError = null;
    protected array $queryLog = [];
    protected bool $enableQueryLog = false;

    public function __construct(protected array $config, ?string $name = null)
    {
        $this->config = $config;
        $this->name = $name;

        // Don't connect immediately - use lazy connection
        // This allows the app to boot even if DB is down
    }

    /**
     * Lazy connection - only connect when actually needed
     */
    protected function connect(): void
    {
        if ($this->connected) {
            return;
        }

        if ($this->connectionError !== null) {
            throw $this->connectionError;
        }

        try {
            $this->createConnection();
            $this->connected = true;
        } catch (DatabaseException $e) {
            $this->connectionError = $e;
            throw $e;
        }
    }

    protected function createConnection(): void
    {
        $driver = $this->config['driver'] ?? 'mysql';
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? null;
        $database = $this->config['database'] ?? null;
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;
        $charset = $this->config['charset'] ?? 'utf8';
        $collation = $this->config['collation'] ?? 'utf8_general_ci';

        // Build DSN based on driver
        switch ($driver) {
            case 'pgsql':
                $port = $port ?? 5432;
                $sslmode = $this->config['sslmode'] ?? 'prefer';
                $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";
                break;

            case 'sqlsrv':
            case 'mssql':
                $dsn = "{$driver}:Server={$host}," . ($port ?? 1433) . ";Database={$database}";
                break;

            case 'sqlite':
                $dsn = "sqlite:" . ($database ?? ':memory:');
                break;

            case 'mysql':
            default:
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                break;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);

            // Set encoding depending on driver
            if ($driver === 'mysql') {
                $this->pdo->exec("SET NAMES '{$charset}' COLLATE '{$collation}'");
            } elseif ($driver === 'pgsql') {
                $this->pdo->exec("SET client_encoding TO '{$charset}'");
            }
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Database connection failed: " . $e->getMessage()
            );
        }
    }

    /**
     * Check if connection is available without throwing
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->pdo !== null;
    }

    /**
     * Check if database is available
     */
    public function canConnect(): bool
    {
        try {
            $this->connect();
            return true;
        } catch (DatabaseException $e) {
            return false;
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPdo(): PDO
    {
        $this->connect();
        return $this->pdo;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function select(string $query, array $bindings = []): array
    {
        $this->connect();
        $statement = $this->run($query, $bindings);
        return $statement->fetchAll();
    }

    public function insert(string $query, array $bindings = []): bool
    {
        $this->connect();
        return $this->statement($query, $bindings);
    }

    public function update(string $query, array $bindings = []): int
    {
        $this->connect();
        return $this->affectingStatement($query, $bindings);
    }

    public function delete(string $query, array $bindings = []): int
    {
        $this->connect();
        return $this->affectingStatement($query, $bindings);
    }

    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings)->rowCount() > 0;
    }

    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings)->rowCount();
    }

    public function unprepared(string $query): bool
    {
        $this->connect();

        $start = microtime(true);

        try {
            $result = $this->pdo->exec($query) !== false;

            if ($this->enableQueryLog) {
                $this->logQuery($query, [], microtime(true) - $start);
            }

            return $result;
        } catch (PDOException $e) {
            throw new DatabaseException("Query failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function prepare(string $query): PDOStatement
    {
        $this->connect();
        try {
            return $this->pdo->prepare($query);
        } catch (PDOException $e) {
            throw new DatabaseException("Failed to prepare statement: " . $e->getMessage(), 0, $e);
        }
    }

    public function raw(string $value): RawExpression
    {
        return new RawExpression($value);
    }

    protected function run(string $query, array $bindings = []): PDOStatement
    {
        $start = microtime(true);

        try {
            $statement = $this->pdo->prepare($query);

            foreach ($bindings as $key => $value) {
                $statement->bindValue(
                    is_string($key) ? $key : $key + 1,
                    $value,
                    $this->getPdoType($value)
                );
            }

            $statement->execute();

            if ($this->enableQueryLog) {
                $this->logQuery($query, $bindings, microtime(true) - $start);
            }

            return $statement;
        } catch (PDOException $e) {
            throw new DatabaseException("Query failed: " . $e->getMessage() . " SQL: {$query}", 0, $e);
        }
    }

    protected function getPdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    public function beginTransaction(): bool
    {
        $this->connect();

        if ($this->transactionLevel === 0) {
            $result = $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT trans{$this->transactionLevel}");
            $result = true;
        }

        $this->transactionLevel++;
        return $result;
    }

    public function commit(): bool
    {
        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            return $this->pdo->commit();
        }

        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactionLevel === 0) {
            throw new DatabaseException("No active transaction");
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            return $this->pdo->rollBack();
        }

        $this->pdo->exec("ROLLBACK TO SAVEPOINT trans{$this->transactionLevel}");
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    public function lastInsertId(): string|false
    {
        $this->connect();
        return $this->pdo->lastInsertId();
    }

    /**
     * Store procedure method
     */
    public function procedure(string $procedure, array $params = []): array
    {
        $this->connect();

        $driver = $this->config['driver'] ?? 'mysql';
        $placeholders = implode(',', array_fill(0, count($params), '?'));

        switch ($driver) {
            case 'sqlsrv':
            case 'mssql':
                $sql = "EXEC {$procedure}" . ($placeholders ? " {$placeholders}" : '');
                break;

            case 'mysql':
            default:
                $sql = "CALL {$procedure}" . ($placeholders ? "({$placeholders})" : '()');
                break;
        }

        return $this->select($sql, $params);
    }

    /**
     * Enable query logging
     */
    public function enableQueryLog(): void
    {
        $this->enableQueryLog = true;
    }

    /**
     * Disable query logging
     */
    public function disableQueryLog(): void
    {
        $this->enableQueryLog = false;
    }

    /**
     * Check if query logging is enabled
     */
    public function isQueryLogEnabled(): bool
    {
        return $this->enableQueryLog;
    }

    /**
     * Get all logged queries
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Flush the query log
     */
    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Log a query
     */
    protected function logQuery(string $query, array $bindings, float $time): void
    {
        $this->queryLog[] = new QueryLog(
            query: $query,
            bindings: $bindings,
            time: $time,
            connection: $this->name
        );

        $boundQuery = $this->formatQueryWithBindings($query, $bindings);
        logger()->debug(
            sprintf('[DB Query] %s (%.2fms)', $boundQuery, $time * 1000),
            [
                'connection' => $this->name,
                'time' => $time,
                'query' => $query,
                'bindings' => $bindings,
            ]
        );
    }

    /**
     * Format query with bindings for logging
     */
    protected function formatQueryWithBindings(string $query, array $bindings): string
    {
        if (empty($bindings)) {
            return $query;
        }

        $formattedBindings = array_map(function ($value) {
            if (is_null($value)) {
                return 'NULL';
            }
            if (is_bool($value)) {
                return $value ? 'TRUE' : 'FALSE';
            }
            if (is_numeric($value)) {
                return (string) $value;
            }
            return "'" . addslashes($value) . "'";
        }, $bindings);

        // Replace ? placeholders
        $position = 0;
        return preg_replace_callback('/\?/', function () use ($formattedBindings, &$position) {
            return $formattedBindings[$position++] ?? '?';
        }, $query);
    }
}
