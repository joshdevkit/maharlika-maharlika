<?php

namespace Maharlika\Broadcasting;

class Channel
{
    public function __construct(
        public readonly string $name
    ) {}

    /**
     * Create a new channel instance
     */
    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * Get the channel name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Convert to string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
