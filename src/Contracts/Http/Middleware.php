<?php

namespace Maharlika\Contracts\Http;

interface Middleware
{
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface;
}