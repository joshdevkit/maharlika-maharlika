<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class In extends Rule
{
    protected array $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return in_array($value, $this->values, true);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The selected $field is invalid.";
    }
}