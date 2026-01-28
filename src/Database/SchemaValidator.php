<?php

namespace Maharlika\Database;

use Maharlika\Contracts\Database\ConnectionInterface;
use Maharlika\Exceptions\QueryException;

class SchemaValidator
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected array $schemaCache = []
    )
    {

    }

    /**
     * Validate data before insert operation
     */
    public function validateInsert(string $table, array $data, string $sql = '', array $bindings = []): void
    {
        $schema = $this->getTableSchema($table);
        $requiredColumns = $this->getRequiredColumns($schema);
        $dataKeys = array_keys($data);
        $missing = array_diff($requiredColumns, $dataKeys);

        if (!empty($missing)) {
            $missingFields = implode(', ', array_map(fn($f) => "`{$f}`", $missing));

            $interpolatedSql = $this->interpolateQuery($sql, $bindings);
            throw new QueryException(
                "SQLSTATE[HY000]: General error: 1364 Field {$missingFields} doesn't have a default value. "
                    . "(SQL: " . $interpolatedSql . ") "
            );
        }

        $this->validateNullableConstraints($schema, $data, $sql, $bindings);
    }

    /**
     * Interpolate query with bindings for error messages
     */
    protected function interpolateQuery(string $sql, array $bindings): string
    {
        if (empty($sql)) {
            return '';
        }

        $interpolatedSql = $sql;
        foreach ($bindings as $binding) {
            if (is_string($binding)) {
                $binding = "'" . str_replace("'", "''", $binding) . "'";
            } elseif ($binding === null) {
                $binding = 'NULL';
            } elseif (is_bool($binding)) {
                $binding = $binding ? '1' : '0';
            }
            $interpolatedSql = preg_replace('/\?/', $binding, $interpolatedSql, 1);
        }

        return $interpolatedSql;
    }

    /**
     * Validate data before update operation
     */
    public function validateUpdate(string $table, array $data, string $sql = '', array $bindings = []): void
    {
        $schema = $this->getTableSchema($table);

        foreach ($data as $column => $value) {
            if ($value === null && isset($schema[$column])) {
                $columnInfo = $schema[$column];
                $isNullable = $columnInfo['nullable'] ?? true;

                if (!$isNullable) {

                    $interpolatedSql = $this->interpolateQuery($sql, $bindings);

                    throw new QueryException(
                        "SQLSTATE[HY000]: 1048 Column `{$column}` cannot be null. (Connection: mysql, SQL: " . $interpolatedSql . ")"
                    );
                }
            }
        }
    }

    /**
     * Get required columns (non-nullable without defaults)
     */
    protected function getRequiredColumns(array $schema): array
    {
        $required = [];

        foreach ($schema as $column => $info) {
            if (
                !$info['nullable'] &&
                $info['default'] === null &&
                !$info['auto_increment']
            ) {
                $required[] = $column;
            }
        }

        return $required;
    }

    /**
     * Validate nullable constraints
     */
    protected function validateNullableConstraints(array $schema, array $data, string $sql = '', array $bindings = []): void
    {
        foreach ($data as $column => $value) {
            if ($value === null && isset($schema[$column])) {
                $columnInfo = $schema[$column];

                if (!$columnInfo['nullable']) {

                    $interpolatedSql = $this->interpolateQuery($sql, $bindings);

                    throw new QueryException(
                        "SQLSTATE[HY000]: 1048 Column `{$column}` cannot be null. (Connection: mysql, SQL: "
                            . $interpolatedSql . ")"
                    );
                }
            }
        }
    }

    /**
     * Get table schema information
     */
    protected function getTableSchema(string $table): array
    {
        // Return cached schema if available
        if (isset($this->schemaCache[$table])) {
            return $this->schemaCache[$table];
        }

        $driver = $this->connection->getConfig()['driver'] ?? 'mysql';
        $columns = $this->getColumnsInfo($table, $driver);

        $schema = [];
        foreach ($columns as $column) {
            $field = $this->extractField($column);
            $schema[$field] = [
                'nullable' => $this->isNullable($column),
                'default' => $this->getDefault($column),
                'auto_increment' => $this->isAutoIncrement($column),
                'type' => $this->getColumnType($column),
            ];
        }

        // Cache the schema
        $this->schemaCache[$table] = $schema;

        return $schema;
    }

    /**
     * Get columns information based on driver
     */
    protected function getColumnsInfo(string $table, string $driver): array
    {
        switch ($driver) {
            case 'pgsql':
                $query = "
                    SELECT 
                        column_name as Field,
                        is_nullable as Null,
                        column_default as Default,
                        data_type as Type,
                        '' as Extra
                    FROM information_schema.columns
                    WHERE table_name = ?
                    ORDER BY ordinal_position
                ";
                return $this->connection->select($query, [$table]);

            case 'sqlite':
                $query = "PRAGMA table_info(`{$table}`)";
                $columns = $this->connection->select($query);
                // Transform SQLite format to match MySQL format
                return array_map(function ($col) {
                    return [
                        'Field' => $col['name'],
                        'Null' => $col['notnull'] == 0 ? 'YES' : 'NO',
                        'Default' => $col['dflt_value'],
                        'Type' => $col['type'],
                        'Extra' => $col['pk'] == 1 ? 'auto_increment' : '',
                    ];
                }, $columns);

            case 'sqlsrv':
            case 'mssql':
                $query = "
                    SELECT 
                        c.name as Field,
                        CASE WHEN c.is_nullable = 1 THEN 'YES' ELSE 'NO' END as Null,
                        dc.definition as Default,
                        t.name as Type,
                        CASE WHEN c.is_identity = 1 THEN 'auto_increment' ELSE '' END as Extra
                    FROM sys.columns c
                    INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
                    LEFT JOIN sys.default_constraints dc ON c.default_object_id = dc.object_id
                    WHERE c.object_id = OBJECT_ID(?)
                    ORDER BY c.column_id
                ";
                return $this->connection->select($query, [$table]);

            case 'mysql':
            default:
                return $this->connection->select("SHOW COLUMNS FROM `{$table}`");
        }
    }

    /**
     * Extract field name from column info
     */
    protected function extractField(array|object $column): string
    {
        return is_object($column) ? $column->Field : $column['Field'];
    }

    /**
     * Check if column is nullable
     */
    protected function isNullable(array|object $column): bool
    {
        $null = is_object($column) ? $column->Null : $column['Null'];
        return strtoupper($null) === 'YES';
    }

    /**
     * Get column default value
     */
    protected function getDefault(array|object $column): mixed
    {
        return is_object($column) ? $column->Default : $column['Default'];
    }

    /**
     * Check if column is auto increment
     */
    protected function isAutoIncrement(array|object $column): bool
    {
        $extra = is_object($column) ? ($column->Extra ?? '') : ($column['Extra'] ?? '');
        return stripos($extra, 'auto_increment') !== false ||
            stripos($extra, 'identity') !== false;
    }

    /**
     * Get column type
     */
    protected function getColumnType(array|object $column): string
    {
        return is_object($column) ? $column->Type : $column['Type'];
    }

    /**
     * Clear schema cache for a table
     */
    public function clearCache(?string $table = null): void
    {
        if ($table === null) {
            $this->schemaCache = [];
        } else {
            unset($this->schemaCache[$table]);
        }
    }

    /**
     * Check if a column exists in the table
     */
    public function hasColumn(string $table, string $column): bool
    {
        $schema = $this->getTableSchema($table);
        return isset($schema[$column]);
    }

    /**
     * Get column info
     */
    public function getColumnInfo(string $table, string $column): ?array
    {
        $schema = $this->getTableSchema($table);
        return $schema[$column] ?? null;
    }
}
