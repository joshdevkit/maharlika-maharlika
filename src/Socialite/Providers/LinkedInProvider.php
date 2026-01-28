<?php

namespace Maharlika\Socialite\Providers;

use Maharlika\Contracts\Socialite\UserInterface;
use Maharlika\Socialite\OAuthUser;

class LinkedInProvider extends AbstractProvider
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['r_liteprofile', 'r_emailaddress'];

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
        return $this->buildAuthUrlFromBase('https://www.linkedin.com/oauth/v2/authorization', $state);
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
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];

        // Get profile information
        $profileResponse = $this->httpClient->get('https://api.linkedin.com/v2/me', [
            'headers' => $headers,
        ]);

        $profile = json_decode($profileResponse->getBody()->getContents(), true);

        // Get email address
        $emailResponse = $this->httpClient->get('https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))', [
            'headers' => $headers,
        ]);

        $emailData = json_decode($emailResponse->getBody()->getContents(), true);
        $email = $emailData['elements'][0]['handle~']['emailAddress'] ?? null;

        return array_merge($profile, ['email' => $email]);
    }

    /**
     * Map the raw user array to a User instance.
     *
     * @param array $user
     * @return \Maharlika\Contracts\Socialite\UserInterface
     */
    protected function mapUserToObject(array $user): UserInterface
    {
        $name = ($user['localizedFirstName'] ?? '') . ' ' . ($user['localizedLastName'] ?? '');
        $name = trim($name);

        return (new OAuthUser())
            ->setRaw($user)
            ->map([
                'id' => $user['id'],
                'nickname' => null,
                'name' => $name ?: null,
                'email' => $user['email'] ?? null,
                'avatar' => $user['profilePicture']['displayImage~']['elements'][0]['identifiers'][0]['identifier'] ?? null,
            ]);
    }
}