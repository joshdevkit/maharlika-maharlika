<?php

namespace Maharlika\Database\Schema;

use Maharlika\Support\Str;

class ForeignIdColumn
{
    protected Blueprint $blueprint;
    protected Column $column;
    protected string $name;
    protected string $driver;

    public function __construct(Blueprint $blueprint, Column $column, string $name, string $driver = 'mysql')
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
        $this->name = $name;
        $this->driver = $driver;
    }

    public function constrained(?string $table = null, string $column = 'id'): ForeignKey
    {
        // Infer table name from column name if not provided
        if ($table === null) {
            $table = $this->inferTableName($this->name);
        }

        return $this->blueprint->foreign($this->name)
            ->references($column)
            ->on($table);
    }

    public function nullable(): self
    {
        $this->column->nullable();
        return $this;
    }

    public function unsigned(): self
    {
        $this->column->unsigned();
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->column->default($value);
        return $this;
    }

    public function after(string $column): self
    {
        $this->column->after($column);
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->column->comment($comment);
        return $this;
    }

    protected function inferTableName(string $columnName): string
    {
        $name = Str::replaceLast('_id', '', $columnName);
        return Str::plural($name);
    }

    public function index(?string $indexName = null): self
    {
        $this->blueprint->index($this->name, $indexName);
        return $this;
    }
}
