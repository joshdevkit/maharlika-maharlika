<?php

namespace Maharlika\Contracts\Session;

interface UserSessionsInterface
{
    /**
     * Get all sessions for a specific user
     * 
     * @return \Maharlika\Database\Collection
     */
    public function getUserSessions(mixed $userId);

    /**
     * Destroy all sessions for a specific user
     */
    public function destroyUserSessions(mixed $userId): bool;
}
