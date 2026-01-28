<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class BooleanRule extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return in_array($value, [true, false, 1, 0, '1', '0'], true);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field field must be true or false.";
    }
}
