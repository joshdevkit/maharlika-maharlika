<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Between extends Rule
{
    public function __construct(
        protected int|float $min,
        protected int|float $max
    ) {
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_numeric($value)) {
            return $value >= $this->min && $value <= $this->max;
        }

        if (is_string($value)) {
            $length = mb_strlen($value);
            return $length >= $this->min && $length <= $this->max;
        }

        if (is_array($value)) {
            $count = count($value);
            return $count >= $this->min && $count <= $this->max;
        }

        return false;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The :attribute field must be between :min and :max.";
    }
}
