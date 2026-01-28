<?php

namespace Maharlika\Contracts\Socialite;

interface UserInterface
{
    /**
     * Get the unique identifier for the user.
     *
     * @return string
     */
    public function getId();

    /**
     * Get the nickname / username for the user.
     *
     * @return string|null
     */
    public function getNickname();

    /**
     * Get the full name of the user.
     *
     * @return string|null
     */
    public function getName();

    /**
     * Get the email address of the user.
     *
     * @return string|null
     */
    public function getEmail();

    /**
     * Get the avatar / image URL for the user.
     *
     * @return string|null
     */
    public function getAvatar();

    /**
     * Get the raw user array.
     *
     * @return array
     */
    public function getRaw();

    /**
     * Get the access token for the user.
     *
     * @return string
     */
    public function getToken();

    /**
     * Get the refresh token for the user.
     *
     * @return string|null
     */
    public function getRefreshToken();

    /**
     * Get the expires in seconds for the access token.
     *
     * @return int|null
     */
    public function getExpiresIn();

    /**
     * Set the provider token
     * 
     */
    public function setToken(string $token);
}