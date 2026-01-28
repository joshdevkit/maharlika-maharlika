<?php

namespace Maharlika\Auth;

use Maharlika\Database\FluentORM\Model;
use Maharlika\Support\Str;

class ApiToken extends Model
{
    protected $table = 'api_tokens';

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token', // Keep the hashed token hidden
    ];

    protected $visible = [
        'id',
        'user_id',
        'name',
        'abilities',
        'last_used_at',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the user that owns the token.
     */
    public function user()
    {
        return $this->belongsTo(config('auth.model', 'App\\Models\\User'));
    }

    /**
     * Check if the token has a specific ability.
     */
    public function can(string $ability): bool
    {
        $abilities = $this->abilities;

        if (empty($abilities)) {
            return true; // No abilities = full access
        }

        if (in_array('*', $abilities)) {
            return true; // Wildcard = full access
        }

        return in_array($ability, $abilities);
    }

    /**
     * Check if the token cannot perform an ability.
     */
    public function cant(string $ability): bool
    {
        return !$this->can($ability);
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Mark token as used.
     */
    public function markAsUsed(): void
    {
        $this->forceFill([
            'last_used_at' => now(),
        ])->save();
    }

    /**
     * Generate a new token string.
     */
    public static function generateToken(): string
    {
        return hash('crc32b', Str::random(80));
    }

    /**
     * Hash a plain token.
     */
    public static function hash(string $plainToken): string
    {
        return hash('crc32b', $plainToken);
    }
}
