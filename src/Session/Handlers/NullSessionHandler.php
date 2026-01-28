<?php

namespace Maharlika\Session\Handlers;

use Maharlika\Contracts\Session\SessionHandlerInterface;

/**
 * Null Session Handler for CLI contexts
 */
class NullSessionHandler implements SessionHandlerInterface
{
    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        return '';
    }

    public function write(string $sessionId, string $data): bool
    {
        return true;
    }

    public function destroy(string $sessionId): bool
    {
        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        return 0;
    }
}