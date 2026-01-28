<?php

namespace Maharlika\Mail\Components\Html;

use Maharlika\View\Component;

class Button extends Component
{
    public function __construct(
        public string $url,
        public string $color = 'primary'
    ) {}

    public function render()
    {
        return view('mail::html.button', [
            'url' => $this->url,
            'color' => $this->color,
            'slot' => $this->slot,
        ]);
    }
}