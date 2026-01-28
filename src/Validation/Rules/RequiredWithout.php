<?php

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class RequiredWithout extends Rule
{
    protected array $otherFields;

    /**
     * Create a new RequiredWithout rule instance.
     *
     * @param string ...$otherFields
     */
    public function __construct(string ...$otherFields)
    {
        $this->otherFields = $otherFields;
    }

    /**
     * Determine if the validation rule passes.
     *
     * The field under validation must be present and not empty 
     * when any of the other specified fields are NOT present.
     *
     * @param string $field
     * @param mixed $value
     * @param array $data
     * @return bool
     */
    public function passes(string $field, $value, array $data): bool
    {
        // Check if ALL of the other fields are present
        $allPresent = true;
        foreach ($this->otherFields as $otherField) {
            if (!isset($data[$otherField]) || $data[$otherField] === '' || $data[$otherField] === null) {
                $allPresent = false;
                break;
            }
        }

        // If all other fields are present, this field is not required
        if ($allPresent) {
            return true;
        }

        // If any other field is missing, this field must be present and not empty
        return isset($value) && $value !== '' && $value !== null;
    }

    /**
     * Get the validation error message.
     *
     * @param string $field
     * @return string
     */
    public function message(string $field): string
    {
        if ($this->message) {
            return $this->message;
        }

        $fields = implode(', ', $this->otherFields);
        return "The {$field} field is required when {$fields} is not present.";
    }
}