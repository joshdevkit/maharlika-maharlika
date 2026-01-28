<?php

namespace Maharlika\Socialite\Providers;

use Maharlika\Contracts\Socialite\UserInterface;
use Maharlika\Socialite\OAuthUser;

class FacebookProvider extends AbstractProvider
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['email', 'public_profile'];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ',';

    /**
     * The fields that should be requested from the Graph API.
     *
     * @var array
     */
    protected $fields = ['id', 'name', 'email', 'picture'];

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     * @return string
     */
    protected function getAuthUrl(string $state): string
    {
        return $this->buildAuthUrlFromBase('https://www.facebook.com/v18.0/dialog/oauth', $state);
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
        return 'https://graph.facebook.com/v18.0/oauth/access_token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $meUrl = 'https://graph.facebook.com/v18.0/me';

        $response = $this->httpClient->get($meUrl, [
            'query' => [
                'access_token' => $token,
                'fields' => implode(',', $this->fields),
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
        $avatar = $user['picture']['data']['url'] ?? null;

        return (new OAuthUser())
            ->setRaw($user)
            ->map([
                'id' => $user['id'],
                'nickname' => null,
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'avatar' => $avatar,
            ]);
    }

    /**
     * Set the user fields to request from Facebook.
     *
     * @param array $fields
     * @return static
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;
        return $this;
    }
}