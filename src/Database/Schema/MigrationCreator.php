<?php

namespace Maharlika\Database\Schema;

class MigrationCreator
{
    protected string $migrationsPath;

    public function __construct(string $migrationsPath)
    {
        $this->migrationsPath = rtrim($migrationsPath, '/');
    }

    public function create(string $name): string
    {
        if ($this->exists($name)) {
            throw new \Exception("Migration already exists: {$name}");
        }

        $timestamp = date('Ymd_His');
        $filename = "{$timestamp}_{$name}.php";
        $path = "{$this->migrationsPath}/{$filename}";

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $stub = $this->resolveStub($name);
        
        file_put_contents($path, $stub);

        return $filename;
    }

    public function exists(string $name): bool
    {
        $pattern = "{$this->migrationsPath}/*_{$name}.php";
        return !empty(glob($pattern));
    }

    /**
     * Determine which stub to use and extract table name
     */
    protected function resolveStub(string $name): string
    {
        // Matches: create_users_table or create_users
        if (preg_match('/^create_(\w+)(?:_table)?$/', $name, $matches)) {
            $tableName = $this->stripTableSuffix($matches[1]);
            return $this->getStub('create.stub', [
                '{{ table }}' => $tableName
            ]);
        }

        // Matches: add_column_to_users_table or add_column_to_users
        // Matches: drop_column_from_users_table or drop_column_from_users
        // Matches: modify_column_in_users_table or modify_column_in_users
        if (preg_match('/^(add|drop|modify)_(\w+)_(to|from|in)_(\w+)(?:_table)?$/', $name, $matches)) {
            $tableName = $this->stripTableSuffix($matches[4]);
            return $this->getStub('alter.stub', [
                '{{ table }}' => $tableName
            ]);
        }

        // Default blank stub
        return $this->getStub('blank.stub');
    }

    /**
     * Strip the "_table" suffix from table name if present
     */
    protected function stripTableSuffix(string $tableName): string
    {
        // If the table name ends with "_table", remove it
        if (preg_match('/^(.+)_table$/', $tableName, $matches)) {
            return $matches[1];
        }

        return $tableName;
    }

    /**
     * Load stub file and replace placeholders
     */
    protected function getStub(string $file, array $replacements = []): string
    {
        // Get the framework's stub directory path
        $stubPath = $this->getFrameworkStubsPath() . "/{$file}";

        if (!file_exists($stubPath)) {
            throw new \Exception("Migration stub missing: {$file}");
        }

        $stub = file_get_contents($stubPath);

        foreach ($replacements as $key => $value) {
            $stub = str_replace($key, $value, $stub);
        }

        return $stub;
    }

    /**
     * Get the path to the framework's stub files
     */
    protected function getFrameworkStubsPath(): string
    {
        // Get the directory of this file (MigrationCreator.php)
        // Navigate up to get to the Console/Commands/stubs directory
        return dirname(__DIR__, 2) . '/Console/Commands/stubs';
    }
}