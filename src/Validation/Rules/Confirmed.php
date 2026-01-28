<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Confirmed extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        $confirmationField = $field . '_confirmation';
        return ($data[$field] ?? null) === ($data[$confirmationField] ?? null);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field confirmation does not match.";
    }
}
