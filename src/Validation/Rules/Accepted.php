<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Accepted extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        $acceptable = ['yes', 'on', '1', 1, true, 'true'];
        
        return in_array($value, $acceptable, true);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be accepted.";
    }
}
