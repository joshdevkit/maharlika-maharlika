<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Exists extends Rule
{
    protected string $table;
    protected ?string $column = null;

    public function __construct(string $table, ?string $column = null)
    {
        $this->table = $table;
        $this->column = $column;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;

        // Auto-infer column from field name if not explicitly set
        $column = $this->column ?? $this->inferColumnFromField($field);

        $db = app('db');
        return $db->table($this->table)->where($column, $value)->count() > 0;
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
        return $this->message ?? "The selected $field is invalid.";
    }
}