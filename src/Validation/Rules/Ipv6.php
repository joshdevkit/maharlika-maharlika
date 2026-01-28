<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Ipv6 extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be a valid IPv6 address.";
    }
}
