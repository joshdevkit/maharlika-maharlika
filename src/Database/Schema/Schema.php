<?php

namespace Maharlika\Database\Schema;

class Schema
{
    protected $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the database driver name
     */
    protected function getDriver(): string
    {
        return $this->connection->getConfig()['driver'] ?? 'mysql';
    }

    /**
     * Create a new table
     */
    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'create', $this->getDriver());
        $callback($blueprint);

        $sql = $blueprint->toSql();
        $this->connection->unprepared($sql);
        
        // For PostgreSQL, create indexes separately
        if ($this->getDriver() === 'pgsql') {
            foreach ($blueprint->getCommands() as $command) {
                if (str_contains($command, 'CREATE INDEX') || str_contains($command, 'CREATE UNIQUE INDEX')) {
                    $this->connection->unprepared($command);
                }
            }
        }
    }

    /**
     * Modify an existing table (alias for alter)
     * This is the Laravel-style method name
     */
    public function table(string $table, callable $callback): void
    {
        $this->alter($table, $callback);
    }

    /**
     * Modify an existing table
     */
    public function alter(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'alter', $this->getDriver());
        $callback($blueprint);

        foreach ($blueprint->getCommands() as $sql) {
            $this->connection->unprepared($sql);
        }
    }

    /**
     * Drop a table (with foreign key handling)
     */
    public function drop(string $table): void
    {
        $driver = $this->getDriver();

        // Disable foreign key checks before dropping
        $this->disableForeignKeyChecks();

        try {
            switch ($driver) {
                case 'pgsql':
                    $this->connection->unprepared("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
                    break;
                case 'sqlsrv':
                case 'mssql':
                    $this->connection->unprepared("IF OBJECT_ID('{$table}', 'U') IS NOT NULL DROP TABLE [{$table}]");
                    break;
                case 'sqlite':
                    // SQLite needs foreign keys disabled first
                    $this->connection->unprepared("DROP TABLE IF EXISTS `{$table}`");
                    break;
                case 'mysql':
                default:
                    $this->connection->unprepared("DROP TABLE IF EXISTS `{$table}`");
                    break;
            }
        } finally {
            // Re-enable foreign key checks
            $this->enableForeignKeyChecks();
        }
    }

    /**
     * Drop a table if it exists (with foreign key handling)
     */
    public function dropIfExists(string $table): void
    {
        if ($this->hasTable($table)) {
            $this->drop($table);
        }
    }

    /**
     * Drop columns from a table
     */
    public function dropColumns(string $table, string|array $columns): void
    {
        $this->table($table, function (Blueprint $blueprint) use ($columns) {
            $columns = is_array($columns) ? $columns : func_get_args();
            array_shift($columns); // Remove $table from args
            
            foreach ((array)$columns as $column) {
                $blueprint->dropColumn($column);
            }
        });
    }

    /**
     * Disable foreign key checks
     */
    public function disableForeignKeyChecks(): void
    {
        $driver = $this->getDriver();

        try {
            switch ($driver) {
                case 'mysql':
                    $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
                    break;
                case 'pgsql':
                    // PostgreSQL doesn't have a global FK check disable
                    // Use CASCADE on DROP instead
                    break;
                case 'sqlite':
                    $this->connection->unprepared('PRAGMA foreign_keys = OFF');
                    break;
                case 'sqlsrv':
                case 'mssql':
                    // SQL Server doesn't have a simple global disable
                    // Individual constraints must be disabled
                    break;
            }
        } catch (\Exception $e) {
            // Ignore if already disabled or not supported
        }
    }

    /**
     * Enable foreign key checks
     */
    public function enableForeignKeyChecks(): void
    {
        $driver = $this->getDriver();

        try {
            switch ($driver) {
                case 'mysql':
                    $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
                    break;
                case 'pgsql':
                    // Not needed for PostgreSQL
                    break;
                case 'sqlite':
                    $this->connection->unprepared('PRAGMA foreign_keys = ON');
                    break;
                case 'sqlsrv':
                case 'mssql':
                    // Not needed for SQL Server
                    break;
            }
        } catch (\Exception $e) {
            // Ignore if already enabled
        }
    }

    /**
     * Check if table exists
     */
    public function hasTable(string $table): bool
    {
        $driver = $this->getDriver();
        switch ($driver) {
            case 'pgsql':
                $schema = $this->connection->getConfig()['schema'] ?? 'public';
                // PostgreSQL stores table names in lowercase by default
                $result = $this->connection->select(
                    "SELECT tablename FROM pg_tables 
                     WHERE schemaname = ? 
                       AND tablename = ?",
                    [$schema, strtolower($table)]
                );
                break;

            case 'sqlsrv':
            case 'mssql':
                $result = $this->connection->select(
                    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_TYPE = 'BASE TABLE' 
                       AND TABLE_NAME = ?",
                    [$table]
                );
                break;

            case 'sqlite':
                $result = $this->connection->select(
                    "SELECT name FROM sqlite_master 
                     WHERE type='table' AND name = ?",
                    [$table]
                );
                break;

            case 'mysql':
            default:
                $result = $this->connection->select(
                    "SELECT TABLE_NAME FROM information_schema.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                    [$table]
                );
                break;
        }

        return !empty($result);
    }

    /**
     * Check if a column exists in a table
     */
    public function hasColumn(string $table, string $column): bool
    {
        $driver = $this->getDriver();
        
        switch ($driver) {
            case 'pgsql':
                $schema = $this->connection->getConfig()['schema'] ?? 'public';
                $result = $this->connection->select(
                    "SELECT column_name FROM information_schema.columns 
                     WHERE table_schema = ? 
                       AND table_name = ? 
                       AND column_name = ?",
                    [$schema, strtolower($table), strtolower($column)]
                );
                break;

            case 'sqlsrv':
            case 'mssql':
                $result = $this->connection->select(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_NAME = ? 
                       AND COLUMN_NAME = ?",
                    [$table, $column]
                );
                break;

            case 'sqlite':
                $result = $this->connection->select("PRAGMA table_info(`{$table}`)");
                foreach ($result as $col) {
                    if ($col['name'] === $column) {
                        return true;
                    }
                }
                return false;

            case 'mysql':
            default:
                $result = $this->connection->select(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = ? 
                       AND COLUMN_NAME = ?",
                    [$table, $column]
                );
                break;
        }

        return !empty($result);
    }

    /**
     * Check if columns exist in a table
     */
    public function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!$this->hasColumn($table, $column)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get the column listing for a table
     */
    public function getColumnListing(string $table): array
    {
        $driver = $this->getDriver();
        
        switch ($driver) {
            case 'pgsql':
                $schema = $this->connection->getConfig()['schema'] ?? 'public';
                $results = $this->connection->select(
                    "SELECT column_name FROM information_schema.columns 
                     WHERE table_schema = ? 
                       AND table_name = ? 
                     ORDER BY ordinal_position",
                    [$schema, strtolower($table)]
                );
                return array_map(fn($r) => $r['column_name'], $results);

            case 'sqlsrv':
            case 'mssql':
                $results = $this->connection->select(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_NAME = ? 
                     ORDER BY ORDINAL_POSITION",
                    [$table]
                );
                return array_map(fn($r) => $r['COLUMN_NAME'], $results);

            case 'sqlite':
                $results = $this->connection->select("PRAGMA table_info(`{$table}`)");
                return array_map(fn($r) => $r['name'], $results);

            case 'mysql':
            default:
                $results = $this->connection->select(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = ? 
                     ORDER BY ORDINAL_POSITION",
                    [$table]
                );
                return array_map(fn($r) => $r['COLUMN_NAME'], $results);
        }
    }

    /**
     * Rename a table
     */
    public function rename(string $from, string $to): void
    {
        $driver = $this->getDriver();

        switch ($driver) {
            case 'pgsql':
                $this->connection->unprepared("ALTER TABLE \"{$from}\" RENAME TO \"{$to}\"");
                break;
            case 'sqlsrv':
            case 'mssql':
                $this->connection->unprepared("EXEC sp_rename '{$from}', '{$to}'");
                break;
            case 'sqlite':
                $this->connection->unprepared("ALTER TABLE `{$from}` RENAME TO `{$to}`");
                break;
            case 'mysql':
            default:
                $this->connection->unprepared("RENAME TABLE `{$from}` TO `{$to}`");
                break;
        }
    }

    /**
     * Get all tables in the database
     */
    public function getAllTables(): array
    {
        $driver = $this->getDriver();
        
        switch ($driver) {
            case 'pgsql':
                $schema = $this->connection->getConfig()['schema'] ?? 'public';
                $results = $this->connection->select(
                    "SELECT tablename FROM pg_tables WHERE schemaname = ?",
                    [$schema]
                );
                return array_map(fn($r) => $r['tablename'], $results);

            case 'sqlsrv':
            case 'mssql':
                $results = $this->connection->select(
                    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'"
                );
                return array_map(fn($r) => $r['TABLE_NAME'], $results);

            case 'sqlite':
                $results = $this->connection->select(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
                );
                return array_map(fn($r) => $r['name'], $results);

            case 'mysql':
            default:
                $results = $this->connection->select(
                    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
                );
                return array_map(fn($r) => $r['TABLE_NAME'], $results);
        }
    }

    /**
     * Drop all tables from the database
     */
    public function dropAllTables(): void
    {
        $this->disableForeignKeyChecks();

        try {
            $tables = $this->getAllTables();
            
            foreach ($tables as $table) {
                $this->drop($table);
            }
        } finally {
            $this->enableForeignKeyChecks();
        }
    }
}