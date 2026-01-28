<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Exists extends Rule
{
    protected string $table;
    protected string $column;

    public function __construct(string $table, string $column = 'id')
    {
        $this->table = $table;
        $this->column = $column;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;

        $db = app('db');
        return $db->table($this->table)->where($this->column, $value)->count() > 0;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The selected $field is invalid.";
    }
}