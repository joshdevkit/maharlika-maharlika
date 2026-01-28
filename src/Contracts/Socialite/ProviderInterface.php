<?php

namespace Maharlika\Contracts\Socialite;

interface ProviderInterface
{
    /**
     * Redirect the user to the authentication page for the provider.
     *
     * @return \Maharlika\Http\RedirectResponse
     */
    public function redirect();

    /**
     * Get the user instance for the authenticated user.
     *
     * @return \Maharlika\Contracts\Socialite\UserInterface
     */
    public function user();

    /**
     * Set the scopes of the requested access.
     *
     * @param array $scopes
     * @return static
     */
    public function scopes(array $scopes): static;

    /**
     * Set whether the provider should request additional scopes from the user.
     *
     * @param array $scopes
     * @return static
     */
    public function setScopes(array $scopes): static;

    /**
     * Indicates that the provider should operate as stateless.
     *
     * @return static
     */
    public function stateless(): static;

    /**
     * Set the custom parameters of the request.
     *
     * @param array $parameters
     * @return static
     */
    public function with(array $parameters): static;
}