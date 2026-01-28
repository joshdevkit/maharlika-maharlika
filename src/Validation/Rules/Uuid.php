<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Uuid extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        return preg_match($pattern, (string) $value) === 1;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be a valid UUID.";
    }
}
