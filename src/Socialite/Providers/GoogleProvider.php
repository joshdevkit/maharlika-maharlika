<?php

namespace Maharlika\Socialite\Providers;

use Maharlika\Contracts\Socialite\UserInterface;
use Maharlika\Socialite\OAuthUser;

class GoogleProvider extends AbstractProvider
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [
        'openid',
        'profile',
        'email',
    ];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     * @return string
     */
    protected function getAuthUrl(string $state): string
    {
        return $this->buildAuthUrlFromBase('https://accounts.google.com/o/oauth2/v2/auth', $state);
    }

    /**
     * Build the authentication URL.
     *
     * @param string $url
     * @param string $state
     * @return string
     */
    protected function buildAuthUrlFromBase(string $url, string $state): string
    {
        return $url . '?' . http_build_query($this->getCodeFields($state), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->httpClient->get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Map the raw user array to a User instance.
     *
     * @param array $user
     * @return \Maharlika\Contracts\Socialite\UserInterface
     */
    protected function mapUserToObject(array $user): UserInterface
    {
        return (new OAuthUser())
            ->setRaw($user)
            ->map([
                'id' => $user['sub'],
                'nickname' => $user['email'],
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'avatar' => $user['picture'] ?? null,
            ]);
    }

    /**
     * Get the code fields for the authentication URL.
     *
     * @param string|null $state
     * @return array
     */
    protected function getCodeFields(?string $state = null): array
    {
        $fields = parent::getCodeFields($state);

        // Google requires access_type=offline for refresh tokens
        if (!isset($fields['access_type'])) {
            $fields['access_type'] = 'online';
        }

        return $fields;
    }
}