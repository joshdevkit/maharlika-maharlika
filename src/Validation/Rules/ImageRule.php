<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class ImageRule extends Rule
{
    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return @getimagesize($value) !== false;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field must be a valid image.";
    }
}