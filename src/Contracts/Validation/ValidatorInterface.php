<?php

namespace Maharlika\Contracts\Validation;

interface ValidatorInterface
{
    /**
     * Determine if the validation fails.
     *
     * @return bool
     */
    public function fails(): bool;

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function errors(): array;

    /**
     * Get the validated data.
     *
     * @return array
     */
    public function validated(): array;

    /**
     * Set the error bag.
     *
     * @param string $errorBag
     * @return void
     */
    public function setErrorBag(string $errorBag): void;

    /**
     * Get the error bag.
     *
     * @return string
     */
    public function getErrorBag(): string;

    /**
     * Add a validation error for a given field.
     *
     * @param string $field
     * @param string $message
     * @param mixed $value
     * @return void
     */
    public function addError(string $field, string $message, $value = null): void;

    /**
     * Add an after validation callback.
     *
     * @param callable $callback
     * @return $this
     */
    public function after(callable $callback): self;

    /**
     * Determine if the validation passes.
     *
     * @return bool
     */
    public function passes(): bool;
    
}