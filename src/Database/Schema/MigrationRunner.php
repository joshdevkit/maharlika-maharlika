<?php

namespace Maharlika\Database\Schema;

use Maharlika\Database\Capsule;
use DirectoryIterator;

/**
 * Migration Runner - Handles executing migrations
 */
class MigrationRunner
{
    protected string $migrationsPath;
    protected string $migrationsTable = 'migrations';
    

    public function __construct(string $migrationsPath)
    {
        $this->migrationsPath = rtrim($migrationsPath, '/');
        $this->ensureMigrationsTableExists();
    }

    /**
     * Run all pending migrations
     * 
     * @return array Array of migration results with status
     */
    public function run(): array
    {
        $migrations = $this->getPendingMigrations();
        
        if (empty($migrations)) {
            return [];
        }

        $ran = [];
        $batch = $this->getNextBatchNumber();

        foreach ($migrations as $migration) {
            try {
                $this->runMigration($migration, $batch);
                $ran[] = [
                    'name' => $migration['name'],
                    'status' => 'success'
                ];
            } catch (\Exception $e) {
                $ran[] = [
                    'name' => $migration['name'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                
                // Stop processing further migrations on error
                break;
            }
        }

        return $ran;
    }

    /**
     * Rollback the last batch of migrations
     */
    public function rollback(int $steps = 1): array
    {
        $rolledBack = [];

        for ($i = 0; $i < $steps; $i++) {
            $batch = $this->getLastBatch();
            
            if (empty($batch)) {
                break;
            }

            foreach (array_reverse($batch) as $migration) {
                try {
                    $this->rollbackMigration($migration);
                    $rolledBack[] = [
                        'name' => $migration['name'],
                        'status' => 'success'
                    ];
                } catch (\Exception $e) {
                    $rolledBack[] = [
                        'name' => $migration['name'],
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                    break;
                }
            }
        }

        return $rolledBack;
    }

    /**
     * Reset all migrations
     */
    public function reset(): array
    {
        $migrations = $this->getRanMigrations();
        $rolledBack = [];

        foreach (array_reverse($migrations) as $migration) {
            try {
                $this->rollbackMigration($migration);
                $rolledBack[] = [
                    'name' => $migration['name'],
                    'status' => 'success'
                ];
            } catch (\Exception $e) {
                $rolledBack[] = [
                    'name' => $migration['name'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                break;
            }
        }

        return $rolledBack;
    }

    /**
     * Refresh all migrations (reset + migrate)
     */
    public function refresh(): array
    {
        $this->reset();
        return $this->run();
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $files = $this->getAllMigrationFiles();
        $ran = $this->getRanMigrations();
        $ranNames = array_column($ran, 'name');

        $status = [];
        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            $status[] = [
                'name' => $name,
                'ran' => in_array($name, $ranNames),
                'batch' => $this->getBatchNumber($name, $ran)
            ];
        }

        return $status;
    }

    /**
     * Run a single migration
     */
    protected function runMigration(array $migration, int $batch): void
    {
        $instance = $this->loadMigration($migration['file']);
        
        $instance->up();
        $this->recordMigration($migration['name'], $batch);
    }

    /**
     * Rollback a single migration
     */
    protected function rollbackMigration(array $migration): void
    {
        $file = $this->findMigrationFile($migration['name']);
        
        if (!$file) {
            throw new \RuntimeException("Migration file not found: {$migration['name']}");
        }

        $instance = $this->loadMigration($file);
        
        $instance->down();
        $this->removeMigration($migration['name']);
    }

    /**
     * Load migration instance from file
     */
    protected function loadMigration(string $file): Migration
    {
        $migration = require $file;
        
        if (!$migration instanceof Migration) {
            throw new \RuntimeException("Migration file must return an instance of Migration: {$file}");
        }

        return $migration;
    }

    /**
     * Get all migration files
     */
    protected function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = [];
        $iterator = new DirectoryIterator($this->migrationsPath);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Get pending migrations
     */
    protected function getPendingMigrations(): array
    {
        $files = $this->getAllMigrationFiles();
        $ran = array_column($this->getRanMigrations(), 'name');
        $pending = [];

        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            
            if (!in_array($name, $ran)) {
                $pending[] = [
                    'name' => $name,
                    'file' => $file
                ];
            }
        }

        return $pending;
    }

    /**
     * Get migrations that have been run
     */
    protected function getRanMigrations(): array
    {
        $results = Capsule::table($this->migrationsTable)
            ->select(['id', 'name', 'batch', 'migrated_at'])
            ->orderBy('id', 'asc')
            ->get();

        // Convert Collection to array of arrays
        return array_map(function($item) {
            // Handle both objects and arrays
            if (is_object($item)) {
                return (array) $item;
            }
            return $item;
        }, $results->toArray());
    }

    /**
     * Get last batch of migrations
     */
    protected function getLastBatch(): array
    {
        $lastBatch = Capsule::table($this->migrationsTable)
            ->max('batch');

        if (!$lastBatch) {
            return [];
        }

        $results = Capsule::table($this->migrationsTable)
            ->select(['id', 'name', 'batch', 'migrated_at'])
            ->where('batch', $lastBatch)
            ->orderBy('id', 'desc')
            ->get();

        // Convert Collection to array of arrays
        return array_map(function($item) {
            if (is_object($item)) {
                return (array) $item;
            }
            return $item;
        }, $results->toArray());
    }

    /**
     * Record a migration
     */
    protected function recordMigration(string $name, int $batch): void
    {
        Capsule::table($this->migrationsTable)->insert([
            'name' => $name,
            'batch' => $batch,
            'migrated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Remove a migration record
     */
    protected function removeMigration(string $name): void
    {
        Capsule::table($this->migrationsTable)
            ->where('name', $name)
            ->delete();
    }

    /**
     * Get next batch number
     */
    protected function getNextBatchNumber(): int
    {
        $max = Capsule::table($this->migrationsTable)->max('batch');
        return ($max ?? 0) + 1;
    }

    /**
     * Get batch number for a migration
     */
    protected function getBatchNumber(string $name, array $ran): ?int
    {
        foreach ($ran as $migration) {
            if ($migration['name'] === $name) {
                return $migration['batch'];
            }
        }
        return null;
    }

    /**
     * Find migration file by name
     */
    protected function findMigrationFile(string $name): ?string
    {
        $files = $this->getAllMigrationFiles();
        
        foreach ($files as $file) {
            if ($this->getMigrationName($file) === $name) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Get migration name from file
     */
    protected function getMigrationName(string $file): string
    {
        return basename($file, '.php');
    }

    /**
     * Ensure migrations table exists
     */
    protected function ensureMigrationsTableExists(): void
    {
        $schema = new Schema(Capsule::connection());

        if (!$schema->hasTable($this->migrationsTable)) {
            $schema->create($this->migrationsTable, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('batch');
                $table->datetime('migrated_at');
            });
        }
    }
}