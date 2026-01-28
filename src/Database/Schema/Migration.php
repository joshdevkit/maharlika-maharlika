<?php

namespace Maharlika\Database\Schema;


/**
 * Base Migration Class
 */
abstract class Migration
{
    /**
     * Run the migration
     */
    abstract public function up(): void;

    /**
     * Reverse the migration
     */
    abstract public function down(): void;

    /**
     * Get migration name from class name
     */
    public function getName(): string
    {
        return static::class;
    }
}