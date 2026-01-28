<?php

namespace Maharlika\Contracts\Routing;

use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;

interface AuthorizeHandlerInterface
{
    /**
     * Handle the authorization check
     * 
     * @param RequestInterface $request
     * @param array $params Additional parameters from the attribute
     * @return ResponseInterface|null Return a response to block, or null to allow
     */
    public function handle(RequestInterface $request, array $params = []): ?ResponseInterface;
}