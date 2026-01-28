<?php

namespace Maharlika\Framework\Components;

use Maharlika\View\Component;

class ApiMethodBadge extends Component
{
    public function __construct(
        public string $method
    ) {}

    public function render()
    {
        return view('framework::components.api-method-badge');
    }

    public function getColor(): string
    {
        return match($this->method) {
            'GET' => 'bg-green-100 text-green-800 ring-green-600/20',
            'POST' => 'bg-blue-100 text-blue-800 ring-blue-600/20',
            'PUT' => 'bg-yellow-100 text-yellow-800 ring-yellow-600/20',
            'PATCH' => 'bg-orange-100 text-orange-800 ring-orange-600/20',
            'DELETE' => 'bg-red-100 text-red-800 ring-red-600/20',
            default => 'bg-gray-100 text-gray-800 ring-gray-600/20',
        };
    }
}