<?php

namespace Maharlika\Mail\Components\Html;

use Maharlika\View\Component;

class Panel extends Component
{
    public function __construct(
        public ?string $title = null,
        public string $background = '#f8f9fa'
    ) {}

    public function render()
    {
        return view('mail::html.panel', [
            'title' => $this->title,
            'background' => $this->background,
            'slot' => $this->slot,
        ]);
    }
}