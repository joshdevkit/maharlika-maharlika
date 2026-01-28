<?php

namespace Maharlika\Mail\Mailable;

class Content
{
    public function __construct(
        public ?string $view = null,
        public ?string $html = null,
        public ?string $text = null,
        public array $with = [],
    ) {}
}
