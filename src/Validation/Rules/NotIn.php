<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class NotIn extends Rule
{
    protected array $values;

    public function __construct(...$values)
    {
        $this->values = is_array($values[0] ?? null) ? $values[0] : $values;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return !in_array($value, $this->values, true);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The selected $field is invalid.";
    }
}