<?php

namespace Maharlika\Facades;

/**
 * @method static void create(string $table, callable $callback)
 * @method static void table(string $table, callable $callback)
 * @method static void alter(string $table, callable $callback)
 * @method static void drop(string $table)
 * @method static void dropIfExists(string $table)
 * @method static void dropColumns(string $table, string|array $columns)
 * @method static void rename(string $from, string $to)
 * @method static void disableForeignKeyChecks()
 * @method static void enableForeignKeyChecks()
 * @method static bool hasTable(string $table)
 * @method static bool hasColumn(string $table, string $column)
 * @method static bool hasColumns(string $table, array $columns)
 * @method static array getColumnListing(string $table)
 * @method static array getAllTables()
 * @method static void dropAllTables()
 * 
 * @see \Maharlika\Database\Schema\Schema
 */
class Schema extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'schema';
    }
}