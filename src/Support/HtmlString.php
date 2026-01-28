<?php

declare(strict_types=1);

namespace Maharlika\Support;

use Maharlika\Contracts\Support\Htmlable;

/**
 * Marks a string as safe HTML that shouldn't be escaped.
 */
class HtmlString implements Htmlable, \Stringable
{
    protected string $html;

    public function __construct(string $html)
    {
        $this->html = $html;
    }


    /**
     * Get the HTML string.
     */
    public function toHtml(): string
    {
        return $this->html;
    }

    /**
     * Determine if the given HTML string is empty.
     */
    public function isEmpty(): bool
    {
        return $this->html === '';
    }

    /**
     * Determine if the given HTML string is not empty.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get the HTML string.
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }
}