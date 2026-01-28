<?php

namespace Maharlika\Contracts\Support;

interface Htmlable
{
    /**
     * Get content as a string of HTML.
     */
    public function toHtml(): string;
}