<?php

namespace Maharlika\Framework\Components;

use Maharlika\View\Component;

class ApiRouteCard extends Component
{
    public function __construct(
        public array $route
    ) {}

    public function render()
    {
        return view('framework::components.api-route-card');
    }

    public function getMethodColor(): string
    {
        return match($this->route['method']) {
            'GET' => 'bg-green-100 text-green-800',
            'POST' => 'bg-blue-100 text-blue-800',
            'PUT' => 'bg-yellow-100 text-yellow-800',
            'PATCH' => 'bg-orange-100 text-orange-800',
            'DELETE' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}