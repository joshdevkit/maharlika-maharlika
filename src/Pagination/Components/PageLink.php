<?php

namespace Maharlika\Pagination\Components;

use Maharlika\View\Component;

class PageLink extends Component
{
    public function __construct(
        public int $page,
        public string $url,
        public bool $current = false
    ) {}

    public function render()
    {
        return view('pagination::page-link');
    }
}
