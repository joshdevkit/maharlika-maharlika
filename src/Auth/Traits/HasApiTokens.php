<?php

namespace Maharlika\Auth\Traits;

use DateTimeInterface;
use Maharlika\Auth\ApiToken;
use Maharlika\Support\Str;

trait HasApiTokens
{
    /**
     * Get all API tokens for the user.
     */
    public function tokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * Get the current access token being used by the request.
     */
    public function currentAccessToken(): ?ApiToken
    {
        return app('auth.api.token');
    }

    /**
     * Create a new API token for the user.
     *
     * @param string $name Token name
     * @param array $abilities Token abilities/permissions
     * @param \DateTimeInterface|null $expiresAt Expiration date
     * @return array ['token' => plaintext, 'accessToken' => model]
     */
    public function createToken(
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ): array {
        $plainTextToken = Str::random(80);
        $hashedToken = ApiToken::hash($plainTextToken);

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => $hashedToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainTextToken,
            'accessToken' => $token,
        ];
    }

    /**
     * Revoke a specific token.
     */
    public function revokeToken(int $tokenId): bool
    {
        return $this->tokens()->where('id', $tokenId)->delete() > 0;
    }

    /**
     * Revoke all tokens.
     */
    public function revokeAllTokens(): int
    {
        return $this->tokens()->delete();
    }

    /**
     * Revoke current access token.
     */
    public function revokeCurrentToken(): bool
    {
        $token = $this->currentAccessToken();

        if (!$token) {
            return false;
        }

        return $token->delete();
    }

    /**
     * Check if the user has a specific ability via their current token.
     */
    public function tokenCan(string $ability): bool
    {
        $token = $this->currentAccessToken();

        return $token && $token->can($ability);
    }

    /**
     * Check if the user cannot perform a specific ability via their current token.
     */
    public function tokenCant(string $ability): bool
    {
        return !$this->tokenCan($ability);
    }
}
