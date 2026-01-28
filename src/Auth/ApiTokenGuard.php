<?php

namespace Maharlika\Auth;

use Maharlika\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Maharlika\Contracts\Http\RequestInterface;

class ApiTokenGuard
{
    protected RequestInterface $request;
    protected string $userEntity;
    protected ?AuthenticatableContract $user = null;
    protected ?ApiToken $token = null;
    protected bool $resolved = false;

    public function __construct(RequestInterface $request, string $userEntity = 'App\\Models\\User')
    {
        $this->request = $request;
        $this->userEntity = $userEntity;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?AuthenticatableContract
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        $token = $this->getTokenFromRequest();

        if (!$token) {
            return null;
        }

        $apiToken = $this->findToken($token);

        if (!$apiToken || $apiToken->isExpired()) {
            return null;
        }

        $this->token = $apiToken;
        $this->user = $apiToken->user;

        // Mark token as used
        $apiToken->markAsUsed();

        app()->instance('auth.api.token', $apiToken);

        return $this->user;
    }

    /**
     * Get the current access token.
     */
    public function token(): ?ApiToken
    {
        if (!$this->resolved) {
            $this->user();
        }

        return $this->token;
    }

    /**
     * Check if user is authenticated.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Check if user is a guest.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get user ID.
     */
    public function id(): mixed
    {
        $user = $this->user();
        return $user ? $user->getAuthIdentifier() : null;
    }

    /**
     * Get token from request.
     */
    protected function getTokenFromRequest(): ?string
    {
        // Check Authorization header (Bearer token)
        $header = $this->request->bearerToken();
        if ($header) {
            return $header;
        }

        // Check query parameter
        $token = $this->request->input('api_token');
        if ($token) {
            return $token;
        }

        return null;
    }

    /**
     * Find token in database.
     */
    protected function findToken(string $plainToken): ?ApiToken
    {
        $hashedToken = ApiToken::hash($plainToken);

        return ApiToken::query()
            ->where('token', $hashedToken)
            ->with('user')
            ->first();
    }

    /**
     * Set the current user.
     */
    public function setUser(?AuthenticatableContract $user): void
    {
        $this->user = $user;
        $this->resolved = true;
    }
}
