<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Min extends Rule
{
    protected int $min;

    public function __construct(int $min)
    {
        $this->min = $min;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;

        if (is_array($value)) {
            return count($value) >= $this->min;
        }

        return strlen((string)$value) >= $this->min;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field must be at least {$this->min}.";
    }
}
