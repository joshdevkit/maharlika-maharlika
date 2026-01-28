<?php

namespace Maharlika\Socialite\Providers;

use Maharlika\Contracts\Socialite\UserInterface;
use Maharlika\Socialite\OAuthUser;

class GitHubProvider extends AbstractProvider
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['user:email'];

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
        return $this->buildAuthUrlFromBase('https://github.com/login/oauth/authorize', $state);
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
        return 'https://github.com/login/oauth/access_token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $userUrl = 'https://api.github.com/user';

        $response = $this->httpClient->get($userUrl, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        $user = json_decode($response->getBody()->getContents(), true);

        // If the user's email is private, we need to fetch it separately
        if (empty($user['email'])) {
            $user['email'] = $this->getEmailByToken($token);
        }

        return $user;
    }

    /**
     * Get the email for the given access token.
     *
     * @param string $token
     * @return string|null
     */
    protected function getEmailByToken(string $token): ?string
    {
        $emailsUrl = 'https://api.github.com/user/emails';

        try {
            $response = $this->httpClient->get($emailsUrl, [
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $emails = json_decode($response->getBody()->getContents(), true);

            foreach ($emails as $email) {
                if ($email['primary'] && $email['verified']) {
                    return $email['email'];
                }
            }

            return $emails[0]['email'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
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
                'nickname' => $user['login'],
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'avatar' => $user['avatar_url'],
            ]);
    }
}