<?php

namespace Maharlika\Socialite;

use Maharlika\Contracts\Session\SessionInterface;
use Maharlika\Contracts\Socialite\ProviderInterface;
use Maharlika\Socialite\Providers\GitHubProvider;
use Maharlika\Socialite\Providers\GoogleProvider;
use Maharlika\Socialite\Providers\FacebookProvider;
use InvalidArgumentException;

class SocialiteManager
{
    /**
     * The session instance.
     *
     * @var \Maharlika\Contracts\Session\SessionInterface
     */
    protected $session;

    /**
     * The configuration array.
     *
     * @var array
     */
    protected $config;

    /**
     * The registered custom drivers.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * Create a new Socialite manager instance.
     *
     * @param \Maharlika\Contracts\Session\SessionInterface $session
     * @param array $config
     */
    public function __construct(SessionInterface $session, array $config)
    {
        $this->session = $session;
        $this->config = $config;
    }

    /**
     * Get a driver instance.
     *
     * @param string|null $driver
     * @return \Maharlika\Contracts\Socialite\ProviderInterface
     */
    public function driver(?string $driver = null): ProviderInterface
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return \Maharlika\Contracts\Socialite\ProviderInterface
     * @throws \InvalidArgumentException
     */
    protected function createDriver(string $driver): ProviderInterface
    {
        // Check if a custom creator exists
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        // Try to create a built-in driver
        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [{$driver}] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     * @return \Maharlika\Contracts\Socialite\ProviderInterface
     */
    protected function callCustomCreator(string $driver): ProviderInterface
    {
        return $this->customCreators[$driver]($this->session, $this->config[$driver] ?? []);
    }

    /**
     * Create an instance of the GitHub driver.
     *
     * @return \Maharlika\Socialite\Providers\GitHubProvider
     */
    protected function createGithubDriver()
    {
        $config = $this->config['github'] ?? [];

        return new GitHubProvider(
            $this->session,
            $config['client_id'] ?? '',
            $config['client_secret'] ?? '',
            $config['redirect'] ?? '',
            $config['guzzle'] ?? []
        );
    }

    /**
     * Create an instance of the Google driver.
     *
     * @return \Maharlika\Socialite\Providers\GoogleProvider
     */
    protected function createGoogleDriver()
    {
        $config = $this->config['google'] ?? [];

        return new GoogleProvider(
            $this->session,
            $config['client_id'] ?? '',
            $config['client_secret'] ?? '',
            $config['redirect'] ?? '',
            $config['guzzle'] ?? []
        );
    }

    /**
     * Create an instance of the Facebook driver.
     *
     * @return \Maharlika\Socialite\Providers\FacebookProvider
     */
    protected function createFacebookDriver()
    {
        $config = $this->config['facebook'] ?? [];

        return new FacebookProvider(
            $this->session,
            $config['client_id'] ?? '',
            $config['client_secret'] ?? '',
            $config['redirect'] ?? '',
            $config['guzzle'] ?? []
        );
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param \Closure $callback
     * @return static
     */
    public function extend(string $driver, \Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'github';
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}