<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class AlphaDash extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return preg_match('/^[a-zA-Z0-9_-]+$/', (string) $value) === 1;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must contain only letters, numbers, dashes, and underscores.";
    }
}