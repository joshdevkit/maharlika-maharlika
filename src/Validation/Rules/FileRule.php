<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class FileRule extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return is_file($value);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field must be a valid file.";
    }
}
