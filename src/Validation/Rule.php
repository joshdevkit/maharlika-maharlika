<?php

namespace Maharlika\Validation;

abstract class Rule
{
    protected array $parameters = [];
    
    protected ?string $message = null;

    abstract public function passes(string $field, $value, array $data): bool;
    
    abstract public function message(string $field): string;

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function __toString(): string
    {
        return static::class;
    }
}