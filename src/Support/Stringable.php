<?php

namespace Maharlika\Support;

class Stringable
{
    public function __construct(
        protected string $value
    ) {}

    public function __toString()
    {
        return $this->value;
    }

    public function value()
    {
        return $this->value;
    }

    public function explode(string $delimiter)
    {
        return collect(explode($delimiter, $this->value));
    }

    public function before(string $search)
    {
        return new static(Str::before($this->value, $search));
    }

    public function after(string $search)
    {
        return new static(Str::after($this->value, $search));
    }

    public function upper()
    {
        return new static(Str::upper($this->value));
    }

    public function lower()
    {
        return new static(Str::lower($this->value));
    }

    public function trim($chars = null)
    {
        return new static(Str::trim($this->value, $chars));
    }
}
