<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class ActiveUrl extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);
        
        if (!$host) {
            return false;
        }

        return checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA') || checkdnsrr($host, 'MX');
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be a valid and active URL.";
    }
}
