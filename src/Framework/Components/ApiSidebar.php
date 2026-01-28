<?php

namespace Maharlika\Framework\Components;

use Maharlika\View\Component;

class ApiSidebar extends Component
{
    public function __construct(
        public array $groupedRoutes
    ) {}

    public function render()
    {
        return view('framework::components.api-sidebar');
    }
}