<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class DigitsBetween extends Rule
{
    public function __construct(
        protected int $min,
        protected int $max
    ) {
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $value = (string) $value;
        $length = mb_strlen($value);
        
        return ctype_digit($value) && $length >= $this->min && $length <= $this->max;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must have between {$this->min} and {$this->max} digits.";
    }
}
