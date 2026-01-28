<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class ArrayRule extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return is_array($value);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field must be an array.";
    }
}