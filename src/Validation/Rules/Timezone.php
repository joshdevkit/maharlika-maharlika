<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Timezone extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array($value, \DateTimeZone::listIdentifiers(), true);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be a valid timezone.";
    }
}
