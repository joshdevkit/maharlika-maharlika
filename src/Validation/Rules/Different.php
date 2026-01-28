<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Different extends Rule
{
    protected string $other;

    public function __construct(string $other)
    {
        $this->other = $other;
    }

    public function passes(string $field, $value, array $data): bool
    {
        return ($data[$field] ?? null) !== ($data[$this->other] ?? null);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field and {$this->other} must be different.";
    }
}