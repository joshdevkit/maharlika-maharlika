<?php

namespace Maharlika\Contracts\Session;

interface SessionHandlerInterface
{
    /**
     * Open the session
     */
    public function open(string $savePath, string $sessionName): bool;

    /**
     * Close the session
     */
    public function close(): bool;

    /**
     * Read session data
     */
    public function read(string $sessionId): string|false;

    /**
     * Write session data
     */
    public function write(string $sessionId, string $data): bool;

    /**
     * Destroy a session
     */
    public function destroy(string $sessionId): bool;

    /**
     * Garbage collection
     */
    public function gc(int $maxLifetime): int|false;
}