<?php

namespace Maharlika\Socialite\Providers;

use Maharlika\Contracts\Socialite\ProviderInterface;
use Maharlika\Contracts\Socialite\UserInterface;
use Maharlika\Contracts\Session\SessionInterface;
use Maharlika\Http\RedirectResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

abstract class AbstractProvider implements ProviderInterface
{
    /**
     * The HTTP Client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * The session instance.
     *
     * @var \Maharlika\Contracts\Session\SessionInterface
     */
    protected $session;

    /**
     * The client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * The redirect URL.
     *
     * @var string
     */
    protected $redirectUrl;

    /**
     * The custom parameters to be sent with the authentication request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ',';

    /**
     * Indicates if the session state should be utilized.
     *
     * @var bool
     */
    protected $stateless = false;

    /**
     * Create a new provider instance.
     *
     * @param SessionInterface $session
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUrl
     * @param array $guzzle
     */
    public function __construct(
        SessionInterface $session,
        string $clientId,
        string $clientSecret,
        string $redirectUrl,
        array $guzzle = []
    ) {
        $this->session = $session;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl = $redirectUrl;
        $this->httpClient = new Client($guzzle);
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     * @return string
     */
    abstract protected function getAuthUrl(string $state): string;

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    abstract protected function getTokenUrl(): string;

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     * @return array
     */
    abstract protected function getUserByToken(string $token): array;

    /**
     * Map the raw user array to a User instance.
     *
     * @param array $user
     * @return \Maharlika\Contracts\Socialite\UserInterface
     */
    abstract protected function mapUserToObject(array $user): UserInterface;

    /**
     * Redirect the user to the authentication page for the provider.
     *
     * @return \Maharlika\Http\RedirectResponse
     */
    public function redirect()
    {
        $state = null;

        if (!$this->stateless) {
            $state = $this->getState();
            $this->session->put('oauth_state', $state);
        }

        return new RedirectResponse($this->getAuthUrl($state));
    }

    /**
     * Get the User instance for the authenticated user.
     *
     * @return \Maharlika\Contracts\Socialite\UserInterface
     * @throws \Exception
     */
    public function user()
    {
        if (!$this->stateless) {
            $this->validateState();
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $user = $this->mapUserToObject($this->getUserByToken(
            $token = $this->parseAccessToken($response)
        ));

        return $user->setToken($token)
            ->setRefreshToken($this->parseRefreshToken($response))
            ->setExpiresIn($this->parseExpiresIn($response));
    }

    /**
     * Get the access token response for the given code.
     *
     * @param string $code
     * @return array
     * @throws \Exception
     */
    protected function getAccessTokenResponse(string $code): array
    {
        try {
            $response = $this->httpClient->post($this->getTokenUrl(), [
                'headers' => ['Accept' => 'application/json'],
                'form_params' => $this->getTokenFields($code),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to obtain access token: ' . $e->getMessage());
        }
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     * @return array
     */
    protected function getTokenFields(string $code): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
            'grant_type' => 'authorization_code',
        ];
    }

    /**
     * Get the code from the request.
     *
     * @return string
     */
    protected function getCode(): string
    {
        return $_GET['code'] ?? '';
    }

    /**
     * Validate the state parameter.
     *
     * @return void
     * @throws \Exception
     */
    protected function validateState(): void
    {
        $state = $this->session->pull('oauth_state');

        if (!$state || ($_GET['state'] ?? '') !== $state) {
            throw new \Exception('Invalid state parameter.');
        }
    }

    /**
     * Generate a random state parameter.
     *
     * @return string
     */
    protected function getState(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Parse the access token from the response.
     *
     * @param array $response
     * @return string
     */
    protected function parseAccessToken(array $response): string
    {
        return $response['access_token'];
    }

    /**
     * Parse the refresh token from the response.
     *
     * @param array $response
     * @return string|null
     */
    protected function parseRefreshToken(array $response): ?string
    {
        return $response['refresh_token'] ?? null;
    }

    /**
     * Parse the expires in from the response.
     *
     * @param array $response
     * @return int|null
     */
    protected function parseExpiresIn(array $response): ?int
    {
        return isset($response['expires_in']) ? (int) $response['expires_in'] : null;
    }

    /**
     * Build the authentication URL query parameters.
     *
     * @param string $state
     * @return array
     */
    protected function getCodeFields(?string $state = null): array
    {
        $fields = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'response_type' => 'code',
        ];

        if (!$this->stateless) {
            $fields['state'] = $state;
        }

        return array_merge($fields, $this->parameters);
    }

    /**
     * Format the given scopes.
     *
     * @param array $scopes
     * @param string $separator
     * @return string
     */
    protected function formatScopes(array $scopes, string $separator): string
    {
        return implode($separator, $scopes);
    }

    /**
     * Set the scopes of the requested access.
     *
     * @param array $scopes
     * @return static
     */
    public function scopes(array $scopes): static
    {
        $this->scopes = array_unique(array_merge($this->scopes, $scopes));
        return $this;
    }

    /**
     * Set the request scopes.
     *
     * @param array $scopes
     * @return static
     */
    public function setScopes(array $scopes): static
    {
        $this->scopes = $scopes;
        return $this;
    }

    /**
     * Set the custom parameters of the request.
     *
     * @param array $parameters
     * @return static
     */
    public function with(array $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Indicates that the provider should operate as stateless.
     *
     * @return static
     */
    public function stateless(): static
    {
        $this->stateless = true;
        return $this;
    }

    /**
     * Get the HTTP Client instance.
     *
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * Set the HTTP Client instance.
     *
     * @param \GuzzleHttp\Client $client
     * @return static
     */
    public function setHttpClient(Client $client): static
    {
        $this->httpClient = $client;
        return $this;
    }
}