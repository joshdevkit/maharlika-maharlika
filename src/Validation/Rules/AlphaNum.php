<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class AlphaNum extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return ctype_alnum((string) $value);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must contain only letters and numbers.";
    }
}
