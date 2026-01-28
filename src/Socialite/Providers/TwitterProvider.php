<?php

namespace Maharlika\Socialite\Providers;

use Maharlika\Contracts\Socialite\UserInterface;
use Maharlika\Socialite\OAuthUser;

class TwitterProvider extends AbstractProvider
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['tweet.read', 'users.read'];

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
        return $this->buildAuthUrlFromBase('https://twitter.com/i/oauth2/authorize', $state);
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
        $fields = $this->getCodeFields($state);
        
        // Twitter OAuth2 requires code_challenge for PKCE
        if (!isset($fields['code_challenge'])) {
            $fields['code_challenge'] = $this->generateCodeChallenge();
            $fields['code_challenge_method'] = 'plain';
        }

        return $url . '?' . http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return 'https://api.twitter.com/2/oauth2/token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->httpClient->get('https://api.twitter.com/2/users/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'query' => [
                'user.fields' => 'id,name,username,profile_image_url',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'] ?? [];
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
                'id' => $user['id'],
                'nickname' => $user['username'] ?? null,
                'name' => $user['name'] ?? null,
                'email' => null, // Twitter OAuth2 doesn't provide email by default
                'avatar' => $user['profile_image_url'] ?? null,
            ]);
    }

    /**
     * Generate a code challenge for PKCE.
     *
     * @return string
     */
    protected function generateCodeChallenge(): string
    {
        return bin2hex(random_bytes(32));
    }
}