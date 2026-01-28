<?php

namespace Maharlika\Http\Middlewares;

use Carbon\CarbonInterval;
use Closure;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\Http\Middleware;
use Maharlika\Contracts\Cache\CacheInterface;
use Maharlika\Exceptions\HttpErrorRenderer;
use Maharlika\Http\Response;

class RateLimitMiddleware implements Middleware
{
    /**
     * The cache instance.
     *
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    /**
     * Maximum number of requests allowed.
     *
     * @var int
     */
    protected int $_maxAttempts = 60;

    /**
     * Time window in minutes.
     *
     * @var int
     */
    protected int $_decayMinutes = 1;

    /**
     * Time window in seconds.
     *
     * @var int
     */
    protected int $_decaySeconds = 60;

    /**
     * Custom key prefix for rate limiting.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Whether to use per-user rate limiting (requires authentication).
     *
     * @var bool
     */
    protected bool $perUser;

    /**
     * Magic getter for properties
     */
    public function __get(string $name): mixed
    {
        if ($name === 'maxAttempts') return $this->_maxAttempts;
        if ($name === 'decayMinutes') return $this->_decayMinutes;
        if ($name === 'decaySeconds') return $this->_decaySeconds;
        throw new \RuntimeException("Property {$name} does not exist");
    }

    /**
     * Magic setter to keep decayMinutes and decaySeconds in sync
     */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'maxAttempts') {
            $this->_maxAttempts = $value;
            return;
        }
        if ($name === 'decayMinutes') {
            $this->_decayMinutes = $value;
            $this->_decaySeconds = $value * 60;
            return;
        }
        if ($name === 'decaySeconds') {
            $this->_decaySeconds = $value;
            $this->_decayMinutes = (int) ($value / 60);
            return;
        }
        throw new \RuntimeException("Property {$name} cannot be set");
    }

    /**
     * RateLimitMiddleware constructor.
     */
    public function __construct(
        int|array $maxAttemptsOrConfig = 60,
        int $decaySeconds = 60,
        string $prefix = 'general',
        bool $perUser = false
    ) {
        // Get cache instance from container
        $this->cache = app('cache')->store();

        if (is_array($maxAttemptsOrConfig)) {
            $config = $maxAttemptsOrConfig;

            if (isset($config[0])) {
                $this->_maxAttempts = $config[0] ?? 60;
                $this->_decaySeconds = $config[1] ?? 60;
                $this->_decayMinutes = (int) ($this->_decaySeconds / 60);
                $this->prefix = $config[2] ?? 'general';
                $this->perUser = $config[3] ?? false;
            } else {
                $this->_maxAttempts = $config['maxAttempts'] ?? $config['max_attempts'] ?? 60;

                if (isset($config['decayMinutes']) || isset($config['decay_minutes'])) {
                    $minutes = $config['decayMinutes'] ?? $config['decay_minutes'];
                    $this->_decayMinutes = $minutes;
                    $this->_decaySeconds = $minutes * 60;
                } else {
                    $this->_decaySeconds = $config['decaySeconds'] ?? $config['decay_seconds'] ?? 60;
                    $this->_decayMinutes = (int) ($this->_decaySeconds / 60);
                }

                $this->prefix = $config['prefix'] ?? 'general';
                $this->perUser = $config['perUser'] ?? $config['per_user'] ?? false;
            }
        } else {
            $this->_maxAttempts = $maxAttemptsOrConfig;
            $this->_decaySeconds = $decaySeconds;
            $this->_decayMinutes = (int) ($decaySeconds / 60);
            $this->prefix = $prefix;
            $this->perUser = $perUser;
        }
    }

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $key = $this->resolveRequestKey($request);

        // Check if IP is blocked (production only, uses database)
        if (app()->isProduction() && $this->isBlockedInDatabase($request)) {
            return $this->buildBlockedResponse($request);
        }

        // Check if too many attempts
        if ($this->tooManyAttempts($key)) {
            // In production, track abuse and potentially block the IP
            if (app()->isProduction()) {
                $this->trackAbuseInDatabase($key, $request);
            }

            return $this->buildRateLimitResponse($key, $request);
        }

        // Increment attempts
        $this->hit($key);

        // Process request
        $response = $next($request);

        // Add rate limit headers
        $this->addHeaders($response, $key);

        return $response;
    }

    /**
     * Check if the IP is blocked in the database
     */
    protected function isBlockedInDatabase(RequestInterface $request): bool
    {
        try {
            return BlockedIpManager::isBlocked($request->ip());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Track abuse in the database and block if necessary
     */
    protected function trackAbuseInDatabase(string $key, RequestInterface $request): void
    {
        try {
            $abuseKey = $key . ':abuse';
            $abuseCount = (int) $this->cache->get($abuseKey, 0);
            
            $abuseCount++;
            $this->cache->put($abuseKey, $abuseCount, 3600);

            if ($abuseCount >= 5) {
                BlockedIpManager::blockIp(
                    $request->ip(),
                    86400,
                    'rate_limit_abuse',
                    [
                        'user_agent' => $request->header('User-Agent'),
                        'blocked_route' => $request->getPath(),
                    ]
                );
                
                if (function_exists('logger')) {
                    logger()->warning('IP blocked due to rate limit abuse', [
                        'ip' => $request->ip(),
                        'key' => $key,
                        'abuse_count' => $abuseCount,
                        'blocked_until' => date('Y-m-d H:i:s', time() + 86400)
                    ]);
                }

                $this->cache->forget($abuseKey);
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Build the blocked IP response
     */
    protected function buildBlockedResponse(RequestInterface $request): ResponseInterface
    {
        try {
            $block = BlockedIpManager::getBlock($request->ip());
            
            if ($block) {
                BlockedIpManager::recordAttempt($request->ip());
                
                $remainingTime = BlockedIpManager::remainingTime($block);

                if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
                    return response()->json([
                        'error' => 'IP Blocked',
                        'message' => 'Your IP has been blocked due to excessive rate limit violations.',
                        'retry_after' => $remainingTime,
                        'reason' => $block->reason,
                    ], 403);
                }

                $remaining = CarbonInterval::seconds($remainingTime)->cascade()->forHumans([
                    'short' => true,   
                    'parts' => 2,    
                ]);

                $expiresAt = \Carbon\Carbon::parse($block->expires_at);
                $blockedAt = \Carbon\Carbon::parse($block->blocked_at);

                return HttpErrorRenderer::renderSecurityError(
                    'IP Address Blocked',
                    [
                        'acting_middleware' => 'Rate Limiting - IP Block',
                        'solution' => "Your IP has been temporarily blocked due to excessive rate limit violations. Please try again in {$remaining}.",
                        'severity' => 'High',
                        'impact' => "Your IP address has been blocked until " . $expiresAt->format('Y-m-d H:i:s') . " due to repeated abuse of rate limits.",
                        'retry_after' => "{$remainingTime} seconds",
                        'blocked_at' => $blockedAt->format('Y-m-d H:i:s'),
                        'abuse_count' => "{$block->abuse_count} violations",
                    ]
                );
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return HttpErrorRenderer::renderSecurityError(
            'IP Address Blocked',
            [
                'acting_middleware' => 'Rate Limiting - IP Block',
                'solution' => "Your IP has been temporarily blocked due to excessive rate limit violations.",
                'severity' => 'High',
                'impact' => 'Your IP address has been blocked due to repeated abuse of rate limits.',
            ]
        );
    }

    /**
     * Determine if the key has been "accessed" too many times.
     */
    protected function tooManyAttempts(string $key): bool
    {
        if ($this->attempts($key) >= $this->_maxAttempts) {
            if ($this->cache->has($key . ':timer')) {
                return true;
            }

            $this->resetAttempts($key);
        }

        return false;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     */
    protected function hit(string $key): int
    {
        $this->cache->add(
            $key . ':timer',
            time() + $this->_decaySeconds,
            $this->_decaySeconds
        );

        $added = $this->cache->add($key, 0, $this->_decaySeconds);
        $hits = (int) $this->cache->increment($key);

        if (!$added && $hits === 1) {
            $this->cache->put($key, 1, $this->_decaySeconds);
        }

        return $hits;
    }

    /**
     * Get the number of attempts for the given key.
     */
    protected function attempts(string $key): int
    {
        return (int) $this->cache->get($key, 0);
    }

    /**
     * Reset the number of attempts for the given key.
     */
    protected function resetAttempts(string $key): bool
    {
        return $this->cache->forget($key);
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     */
    protected function availableIn(string $key): int
    {
        $timer = $this->cache->get($key . ':timer');
        return max(0, $timer - time());
    }

    /**
     * Get the number of remaining attempts.
     */
    protected function remainingAttempts(string $key): int
    {
        return max(0, $this->_maxAttempts - $this->attempts($key));
    }

    /**
     * Resolve the rate limit key for the request.
     */
    protected function resolveRequestKey(RequestInterface $request): string
    {
        if ($this->perUser && function_exists('auth') && auth()->check()) {
            return 'rate_limit:' . $this->prefix . ':user:' . auth()->id();
        }

        return 'rate_limit:' . $this->prefix . ':ip:' . sha1($request->ip());
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addHeaders(ResponseInterface $response, string $key): void
    {
        $response->header('X-RateLimit-Limit', (string) $this->_maxAttempts);
        $response->header('X-RateLimit-Remaining', (string) $this->remainingAttempts($key));

        if ($this->cache->has($key . ':timer')) {
            $resetTime = $this->cache->get($key . ':timer');
            $response->header('X-RateLimit-Reset', (string) $resetTime);
            $response->header('Retry-After', (string) max(0, $resetTime - time()));
        }
    }

    /**
     * Build the rate limit exceeded response.
     */
    protected function buildRateLimitResponse(string $key, RequestInterface $request): ResponseInterface
    {
        $retryAfter = $this->availableIn($key);

        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            $response = response()->json([
                'error' => 'Too Many Requests',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
            ], 429);
        } else {
            $remaining = CarbonInterval::seconds($retryAfter)->cascade()->forHumans([
                'short' => true,   
                'parts' => 2,    
            ]);

            $response = HttpErrorRenderer::renderSecurityError(
                'Too Many Requests',
                [
                    'acting_middleware' => 'Rate Limiting',
                    'solution' => "You have exceeded the rate limit. Please wait {$remaining} before trying again.",
                    'severity' => 'Medium',
                    'impact' => 'Your request has been blocked to prevent abuse. This is a temporary restriction.',
                    'retry_after' => "{$retryAfter} seconds",
                    'max_attempts' => "{$this->_maxAttempts} requests per {$this->_decayMinutes} minute(s)",
                ]
            );
        }

        $response->header('X-RateLimit-Limit', (string) $this->_maxAttempts);
        $response->header('X-RateLimit-Remaining', '0');
        $response->header('Retry-After', (string) $retryAfter);

        if ($this->cache->has($key . ':timer')) {
            $resetTime = $this->cache->get($key . ':timer');
            $response->header('X-RateLimit-Reset', (string) $resetTime);
        }

        return $response;
    }

    /**
     * Create a rate limiter for authenticated users.
     */
    public static function perUser(int $maxAttempts = 100, int $decaySeconds = 60): static
    {
        return new static($maxAttempts, $decaySeconds, 'user', true);
    }

    /**
     * Unblock an IP address (admin utility)
     */
    public static function unblockIp(string $ip): bool
    {
        try {
            $result = BlockedIpManager::unblockIp($ip);
            
            if ($result && function_exists('logger')) {
                logger()->info('IP unblocked manually', ['ip' => $ip]);
            }
            
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if an IP is blocked
     */
    public static function isIpBlocked(string $ip): bool
    {
        try {
            return BlockedIpManager::isBlocked($ip);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all blocked IPs
     */
    public static function getBlockedIps(): array
    {
        try {
            return BlockedIpManager::getActiveBlocks();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get block statistics
     */
    public static function getBlockStatistics(): array
    {
        try {
            return BlockedIpManager::getStatistics();
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
     * Clean up expired blocks
     */
    public static function cleanupExpiredBlocks(): int
    {
        try {
            return BlockedIpManager::cleanupExpired();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Clear all rate limit data for a key
     */
    public static function clearKey(string $key): void
    {
        $cache = app('cache')->store();
        $cache->forget($key);
        $cache->forget($key . ':timer');
        $cache->forget($key . ':abuse');
    }
}