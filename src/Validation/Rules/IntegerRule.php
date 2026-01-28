<?php 


namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class IntegerRule extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field must be an integer.";
    }
}