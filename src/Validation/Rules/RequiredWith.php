<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class RequiredWith extends Rule
{
    protected array $otherFields;

    public function __construct(string ...$otherFields)
    {
        $this->otherFields = $otherFields;
    }

    public function passes(string $field, $value, array $data): bool
    {
        // Check if any of the other fields are present
        $anyPresent = false;
        foreach ($this->otherFields as $otherField) {
            if (isset($data[$otherField]) && $data[$otherField] !== '' && $data[$otherField] !== null) {
                $anyPresent = true;
                break;
            }
        }

        // If none of the other fields are present, this field is not required
        if (!$anyPresent) {
            return true;
        }

        // If any other field is present, this field must also be present and not empty
        return isset($value) && $value !== '' && $value !== null;
    }

    public function message(string $field): string
    {
        if ($this->message) {
            return $this->message;
        }

        $fields = implode(', ', $this->otherFields);
        return "The {$field} field is required when {$fields} is present.";
    }
}