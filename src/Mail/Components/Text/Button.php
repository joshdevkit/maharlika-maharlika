<?php

namespace Maharlika\Mail\Components\Text;

use Maharlika\View\Component;

class Button extends Component
{
    public function __construct(
        public string $url
    ) {}

    public function render()
    {
        return view('mail::text.button', [
            'url' => $this->url,
            'slot' => $this->slot,
        ]);
    }
}