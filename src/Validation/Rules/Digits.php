<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Digits extends Rule
{
    public function __construct(
        protected int $length
    ) {
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $value = (string) $value;
        return ctype_digit($value) && mb_strlen($value) === $this->length;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be exactly {$this->length} digits.";
    }
}
