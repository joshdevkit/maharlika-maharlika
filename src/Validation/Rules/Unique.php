<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Unique extends Rule
{
    protected string $table;
    protected ?string $column = null;
    protected ?string $ignoreColumn = null;
    protected $ignoreValue = null;

    public function __construct(string $table, ?string $column = null)
    {
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Ignore a specific record by column/value (default column is primary key)
     */
    public function ignore($value, ?string $column = null): self
    {
        $this->ignoreValue = $value;
        $this->ignoreColumn = $column;
        return $this;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;

        // Auto-infer column from field name if not explicitly set
        $column = $this->column ?? $this->inferColumnFromField($field);

        $db = app('db');
        $query = $db->table($this->table)->where($column, $value);
        
        if ($this->ignoreValue !== null) {
            $ignoreColumn = $this->ignoreColumn ?? $query->getPrimaryKey($this->table); 
            $query->where($ignoreColumn, '!=', $this->ignoreValue);
        }

        return $query->count() === 0;
    }

    /**
     * Infer the database column name from the field name
     */
    protected function inferColumnFromField(string $field): string
    {
        // If field contains dot notation (nested), use the last part
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            return end($parts);
        }

        return $field;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field has already been taken.";
    }
}