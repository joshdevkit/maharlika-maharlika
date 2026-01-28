<?php

namespace Maharlika\Database;

class RawExpression
{
    public function __construct(
        protected string $value
    ) {}

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
