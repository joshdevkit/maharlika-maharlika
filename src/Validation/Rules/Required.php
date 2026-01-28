<?php


namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Required extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        return isset($data[$field]) && $data[$field] !== '';
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field field is required.";
    }
}