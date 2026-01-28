<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Declined extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        $declined = ['no', 'off', '0', 0, false, 'false'];
        
        return in_array($value, $declined, true);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be declined.";
    }
}
