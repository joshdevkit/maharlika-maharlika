<?php

namespace Maharlika\Mail\Components\Html;

use Maharlika\View\Component;

class Layout extends Component
{
    public function __construct(
        public ?string $title = null
    ) {}

    public function render()
    {
        return view('mail::html.layout', [
            'title' => $this->title,
            'slot' => $this->slot,
        ]);
    }
}