<?php

namespace Maharlika\Mail\Components\Html;

use Maharlika\View\Component;

class Header extends Component
{
    public function __construct(
        public ?string $logo = null
    ) {}

    public function render()
    {
        return view('mail::html.header', [
            'logo' => $this->logo,
            'slot' => $this->slot,
        ]);
    }
}