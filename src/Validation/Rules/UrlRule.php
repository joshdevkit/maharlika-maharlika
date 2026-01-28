<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class UrlRule extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field must be a valid URL.";
    }
}