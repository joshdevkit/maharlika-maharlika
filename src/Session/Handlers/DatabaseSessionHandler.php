<?php

namespace Maharlika\Session\Handlers;

use Maharlika\Contracts\Session\SessionHandlerInterface;
use Maharlika\Contracts\Session\UserSessionsInterface;
use Maharlika\Database\DatabaseManager;
use Maharlika\Facades\Log;

class DatabaseSessionHandler implements SessionHandlerInterface, UserSessionsInterface
{
    protected DatabaseManager $db;
    protected string $table;
    protected int $lifetime;

    public function __construct(DatabaseManager $db, string $table = 'sessions', int $lifetime = 7200)
    {
        $this->db = $db;
        $this->table = $table;
        $this->lifetime = $lifetime;
    }

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
        $result = $this->db->table($this->table)
            ->where('id', $sessionId)
            ->first();

        // first() returns an array or null
        if ($result && isset($result->payload)) {
            $decoded = base64_decode($result->payload);
            return $decoded !== false ? $decoded : '';
        }

        return '';
    }

    public function write(string $sessionId, string $data): bool
    {
        $payload = base64_encode($data);
        $lastActivity = time();

        // Get user ID from auth if available
        $userId = null;
        try {
            if (auth()->check()) {
                $userId = auth()->id();
            }
        } catch (\Exception $e) {
            // Ignore auth errors during session write
        }

        // Get IP and User Agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $existing = $this->db->table($this->table)
            ->where('id', $sessionId)
            ->first();

        if ($existing) {
            // Prepare update data
            $updateData = [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'payload' => $payload,
                'last_activity' => $lastActivity,
            ];

            // Only update user_id if we have one (don't overwrite with NULL)
            if ($userId !== null) {
                $updateData['user_id'] = $userId;
            }

            $this->db->table($this->table)
                ->where('id', $sessionId)
                ->update($updateData);

            return true;
        } else {
            // Insert new session
            return $this->db->table($this->table)
                ->insert([
                    'id' => $sessionId,
                    'user_id' => $userId,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'payload' => $payload,
                    'last_activity' => $lastActivity,
                ]);
        }
    }

    public function destroy(string $sessionId): bool
    {
        $deleted = $this->db->table($this->table)
            ->where('id', $sessionId)
            ->delete();

        return $deleted >= 0; // Return true even if 0 rows deleted
    }

    public function gc(int $maxLifetime): int|false
    {
        $expiration = time() - $maxLifetime;

        $deleted = $this->db->table($this->table)
            ->where('last_activity', '<', $expiration)
            ->delete();

        return $deleted;
    }

    /**
     * Get all sessions for a specific user
     * 
     * @return \Maharlika\Database\Collection
     * @throws \RuntimeException if not using database driver
     */
    public function getUserSessions(mixed $userId)
    {
        if (!$this->db) {
            throw new \RuntimeException(
                'Current driver does not support this feature. update SESSION_DRIVER=database'
            );
        }

        return $this->db->table($this->table)
            ->where('user_id', $userId)
            ->get();
    }

    /**
     * Destroy all sessions for a specific user
     * 
     * @throws \RuntimeException if not using database driver
     */
    public function destroyUserSessions(mixed $userId): bool
    {
        if (!$this->db) {
            throw new \RuntimeException(
                'Current driver does not support this feature. update SESSION_DRIVER=database'
            );
        }

        $deleted = $this->db->table($this->table)
            ->where('user_id', $userId)
            ->delete();

        return $deleted >= 0;
    }
}
