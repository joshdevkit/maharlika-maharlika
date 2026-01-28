<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class EndsWith extends Rule
{
    protected array $needles;

    public function __construct(string ...$needles)
    {
        $this->needles = $needles;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $value = (string) $value;

        foreach ($this->needles as $needle) {
            if (str_ends_with($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function message(string $field): string
    {
        $needles = implode(', ', $this->needles);
        return $this->message ?? "The :attribute field must end with one of: {$needles}.";
    }
}
