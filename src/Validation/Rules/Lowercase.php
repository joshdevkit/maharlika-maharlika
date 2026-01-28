<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Lowercase extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $value = (string) $value;
        return $value === mb_strtolower($value);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be lowercase.";
    }
}
