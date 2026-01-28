<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class DateRule extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return strtotime($value) !== false;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field is not a valid date.";
    }
}