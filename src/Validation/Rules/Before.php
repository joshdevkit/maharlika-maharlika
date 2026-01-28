<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Before extends Rule
{
    protected string $date;

    public function __construct(string $date)
    {
        $this->date = $date;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;
        return strtotime($value) < strtotime($this->date);
    }

    public function message(string $field): string
    {
        return $this->message ?? "The $field must be a date before {$this->date}.";
    }
}