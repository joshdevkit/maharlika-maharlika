<?php

namespace Maharlika\Contracts\Database;

use Maharlika\Database\RawExpression;
use PDO;

/**
 * Interface ConnectionInterface
 *
 * Defines the contract for a database connection.
 * Provides methods for executing queries, transactions, and
 * retrieving results in a consistent way.
 */
interface ConnectionInterface
{
    /**
     * Get the underlying PDO instance.
     *
     * @return PDO The PDO connection object.
     */
    public function getPdo(): PDO;


    /**
     * Get the underlying array config
     * @return array 
     */
    public function getConfig(): array;

    /**
     * Get the active connnection name
     * 
     * @return string
     */

    public function getName(): ?string;

    /**
     * Execute a SELECT query and return the results as an array.
     *
     * @param string $query The SQL query to execute.
     * @param array $bindings Optional bindings for prepared statements.
     * @return array The result set as an array.
     */
    public function select(string $query, array $bindings = []): array;

    /**
     * Execute an INSERT statement.
     *
     * @param string $query The SQL INSERT query.
     * @param array $bindings Optional bindings for prepared statements.
     * @return bool True on success, false on failure.
     */
    public function insert(string $query, array $bindings = []): bool;

    /**
     * Execute an UPDATE statement.
     *
     * @param string $query The SQL UPDATE query.
     * @param array $bindings Optional bindings for prepared statements.
     * @return int The number of affected rows.
     */
    public function update(string $query, array $bindings = []): int;

    /**
     * Execute a DELETE statement.
     *
     * @param string $query The SQL DELETE query.
     * @param array $bindings Optional bindings for prepared statements.
     * @return int The number of affected rows.
     */
    public function delete(string $query, array $bindings = []): int;

    /**
     * Execute a general SQL statement.
     *
     * @param string $query The SQL query to execute.
     * @param array $bindings Optional bindings for prepared statements.
     * @return bool True on success, false on failure.
     */
    public function statement(string $query, array $bindings = []): bool;

    /**
     * Execute a statement that affects rows (INSERT, UPDATE, DELETE).
     *
     * @param string $query The SQL query to execute.
     * @param array $bindings Optional bindings for prepared statements.
     * @return int The number of affected rows.
     */
    public function affectingStatement(string $query, array $bindings = []): int;

    /**
     * Prepare a SQL statement for execution.
     *
     * @param string $query The SQL query to prepare.
     * @return \PDOStatement The prepared statement object.
     */
    public function prepare(string $query): \PDOStatement;

    /**
     * Execute a raw SQL query without preparing it.
     *
     * @param string $query The SQL query to execute.
     * @return bool True on success, false on failure.
     */
    public function unprepared(string $query): bool;

    /**
     * Begin a new database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function beginTransaction(): bool;

    /**
     * Get the ID of the last inserted row.
     *
     * @return string|false The last insert ID, or false on failure.
     */
    public function lastInsertId(): string|false;

    /**
     * Commit the current database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function commit(): bool;

    /**
     * Roll back the current database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function rollBack(): bool;

    /**
     * Determine if a transaction is currently active.
     *
     * @return bool True if in a transaction, false otherwise.
     */
    public function inTransaction(): bool;

    /**
     * Create a raw SQL expression that will not be escaped.
     *
     * @param string $value The raw SQL string.
     * @return RawExpression The raw expression object.
     */
    public function raw(string $value): RawExpression;

    /**
     * Enable query logging
     */
    public function enableQueryLog(): void;

     /**
     * Disable query logging
     */
    public function disableQueryLog(): void;


     /**
     * Check if query logging is enabled
     */
    public function isQueryLogEnabled(): bool;


    /**
     * Get all logged queries
     */
    public function getQueryLog(): array;


    /**
     * Flush the query log
     */
    public function flushQueryLog(): void;
}
