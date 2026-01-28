<?php

namespace Maharlika\Mail\Components\Html;

use Maharlika\View\Component;

class Footer extends Component
{
    public function render()
    {
        return view('mail::html.footer', [
            'slot' => $this->slot,
        ]);
    }
}