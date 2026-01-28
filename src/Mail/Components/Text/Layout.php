<?php

namespace Maharlika\Mail\Components\Text;

use Maharlika\View\Component;

class Layout extends Component
{
    public function render()
    {
        return view('mail::text.layout', [
            'slot' => $this->slot,
        ]);
    }
}