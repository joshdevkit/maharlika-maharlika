<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Max extends Rule
{
    protected int $max;

    public function __construct(int $max)
    {
        $this->max = $max;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return strlen((string)$value) <= $this->max;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field may not be greater than {$this->max} characters.";
    }
}