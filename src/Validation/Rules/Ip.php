<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Ip extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be a valid IP address.";
    }
}
