<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class JsonRule extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field must be a valid JSON string.";
    }
}
