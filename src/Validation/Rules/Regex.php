<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Regex extends Rule
{
    protected string $pattern;

    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return preg_match($this->pattern, $value) === 1;
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field format is invalid.";
    }
}
