<?php

namespace Maharlika\Http\Middlewares;

use Maharlika\Facades\DB;
use Carbon\Carbon;

class BlockedIpManager
{
    /**
     * Block an IP address
     *
     * @param string $ip
     * @param int $durationInSeconds
     * @param string $reason
     * @param array $additionalData
     * @return bool
     */
    public static function blockIp(
        string $ip,
        int $durationInSeconds = 86400,
        string $reason = 'rate_limit_abuse',
        array $additionalData = []
    ): bool {
        $ipHash = hash('sha256', $ip);
        $now = now();
        $expiresAt = $now->copy()->addSeconds($durationInSeconds);

        try {
            // Check if IP is already blocked
            $blocked = DB::table('blocked_ips')
                ->where('ip_hash', $ipHash)
                ->first();

            if ($blocked) {
                // Update existing block
                DB::table('blocked_ips')
                    ->where('ip_hash', $ipHash)
                    ->update([
                        'abuse_count' => $blocked->abuse_count + 1,
                        'expires_at' => $expiresAt,
                        'last_attempt_at' => $now,
                        'user_agent' => $additionalData['user_agent'] ?? $blocked->user_agent,
                        'blocked_route' => $additionalData['blocked_route'] ?? $blocked->blocked_route,
                        'updated_at' => $now,
                    ]);

                return true;
            }

            // Create new block
            DB::table('blocked_ips')->insert([
                'ip_address' => $ip,
                'ip_hash' => $ipHash,
                'abuse_count' => 1,
                'reason' => $reason,
                'user_agent' => $additionalData['user_agent'] ?? null,
                'blocked_route' => $additionalData['blocked_route'] ?? null,
                'blocked_at' => $now,
                'expires_at' => $expiresAt,
                'blocked_by' => $additionalData['blocked_by'] ?? null,
                'notes' => $additionalData['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->error('Failed to block IP', [
                    'ip' => $ip,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Check if an IP is currently blocked
     *
     * @param string $ip
     * @return bool
     */
    public static function isBlocked(string $ip): bool
    {
        $ipHash = hash('sha256', $ip);

        try {
            return DB::table('blocked_ips')
                ->where('ip_hash', $ipHash)
                ->where('expires_at', '>', now())
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get blocked IP record if exists and not expired
     *
     * @param string $ip
     * @return object|null
     */
    public static function getBlock(string $ip): ?object
    {
        $ipHash = hash('sha256', $ip);

        try {
            return DB::table('blocked_ips')
                ->where('ip_hash', $ipHash)
                ->where('expires_at', '>', now())
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Unblock an IP address
     *
     * @param string $ip
     * @return bool
     */
    public static function unblockIp(string $ip): bool
    {
        $ipHash = hash('sha256', $ip);

        try {
            return DB::table('blocked_ips')
                ->where('ip_hash', $ipHash)
                ->delete() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Record an attempt from a blocked IP
     *
     * @param string $ip
     * @return void
     */
    public static function recordAttempt(string $ip): void
    {
        $ipHash = hash('sha256', $ip);

        try {
            DB::table('blocked_ips')
                ->where('ip_hash', $ipHash)
                ->update(['last_attempt_at' => now()]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Clean up expired blocks
     *
     * @return int Number of deleted records
     */
    public static function cleanupExpired(): int
    {
        try {
            return DB::table('blocked_ips')
                ->where('expires_at', '<', now())
                ->delete();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get all currently blocked IPs
     *
     * @return array
     */
    public static function getActiveBlocks(): array
    {
        try {
            return DB::table('blocked_ips')
                ->where('expires_at', '>', now())
                ->orderBy('blocked_at', 'desc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get block statistics
     *
     * @return array
     */
    public static function getStatistics(): array
    {
        try {
            $now = now();

            return [
                'total_blocks' => DB::table('blocked_ips')->count(),
                'active_blocks' => DB::table('blocked_ips')
                    ->where('expires_at', '>', $now)
                    ->count(),
                'expired_blocks' => DB::table('blocked_ips')
                    ->where('expires_at', '<=', $now)
                    ->count(),
                'blocks_today' => DB::table('blocked_ips')
                    ->whereDate('blocked_at', $now)
                    ->count(),
                'top_abusers' => DB::table('blocked_ips')
                    ->where('expires_at', '>', $now)
                    ->orderBy('abuse_count', 'desc')
                    ->limit(10)
                    ->get(['ip_address', 'abuse_count', 'blocked_at', 'reason'])
                    ->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'total_blocks' => 0,
                'active_blocks' => 0,
                'expired_blocks' => 0,
                'blocks_today' => 0,
                'top_abusers' => [],
            ];
        }
    }

    /**
     * Get remaining block time in seconds
     *
     * @param object $block
     * @return int
     */
    public static function remainingTime(object $block): int
    {
        try {
            $expiresAt = Carbon::parse($block->expires_at);
            $now = now();

            // If already expired, return 0
            if ($expiresAt->lessThanOrEqualTo($now)) {
                return 0;
            }

            // Get the difference and ensure it's a positive integer
            return max(0, (int) ceil($expiresAt->diffInSeconds($now, false)));
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Extend the block duration
     *
     * @param string $ip
     * @param int $additionalSeconds
     * @return bool
     */
    public static function extendBlock(string $ip, int $additionalSeconds): bool
    {
        $ipHash = hash('sha256', $ip);

        try {
            $block = DB::table('blocked_ips')
                ->where('ip_hash', $ipHash)
                ->first();

            if (!$block) {
                return false;
            }

            $newExpiresAt = Carbon::parse($block->expires_at)->addSeconds($additionalSeconds);

            return DB::table('blocked_ips')
                ->where('ip_hash', $ipHash)
                ->update(['expires_at' => $newExpiresAt]) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
