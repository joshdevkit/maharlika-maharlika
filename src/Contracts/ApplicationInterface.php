<?php

namespace Maharlika\Contracts;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;

interface ApplicationInterface extends ContainerInterface
{
    /**
     * Get the container instance
     */
    public function getContainer(): ContainerInterface;

    /**
     * Get the base path of the application
     */
    public function basePath(string $path = ''): string;

    /**
     * Boot the application
     */
    public function boot(): void;

    /**
     * Handle an incoming request
     */
    public function handle(RequestInterface $request): ResponseInterface;


    /**
     * Determine if the application has been bootstrapped
     */
    public function hasBeenBootstrapped(): bool;
   
}