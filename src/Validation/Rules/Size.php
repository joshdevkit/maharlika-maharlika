<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Size extends Rule
{
    public function __construct(
        protected int|float $size
    ) {
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_numeric($value)) {
            return $value == $this->size;
        }

        if (is_string($value)) {
            return mb_strlen($value) == $this->size;
        }

        if (is_array($value)) {
            return count($value) == $this->size;
        }

        // For file uploads
        if ($value instanceof \Maharlika\Http\UploadedFile) {
            return $value->getSize() / 1024 == $this->size; // Size in KB
        }

        return false;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be exactly :size.";
    }
}
