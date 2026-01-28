<?php

namespace Maharlika\Socialite;

use Maharlika\Contracts\Socialite\UserInterface;

class OAuthUser implements UserInterface
{
    /**
     * The unique identifier for the user.
     *
     * @var string
     */
    protected $id;

    /**
     * The user's nickname / username.
     *
     * @var string|null
     */
    protected $nickname;

    /**
     * The user's full name.
     *
     * @var string|null
     */
    protected $name;

    /**
     * The user's email address.
     *
     * @var string|null
     */
    protected $email;

    /**
     * The user's avatar image URL.
     *
     * @var string|null
     */
    protected $avatar;

    /**
     * The raw user data.
     *
     * @var array
     */
    protected $user;

    /**
     * The user's access token.
     *
     * @var string
     */
    protected $token;

    /**
     * The user's refresh token.
     *
     * @var string|null
     */
    protected $refreshToken;

    /**
     * The number of seconds until the access token expires.
     *
     * @var int|null
     */
    protected $expiresIn;

    /**
     * Set the raw user data.
     *
     * @param array $user
     * @return static
     */
    public function setRaw(array $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the unique identifier for the user.
     *
     * @param string $id
     * @return static
     */
    public function setId($id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the nickname / username for the user.
     *
     * @return string|null
     */
    public function getNickname()
    {
        return $this->nickname;
    }

    /**
     * Set the nickname / username for the user.
     *
     * @param string|null $nickname
     * @return static
     */
    public function setNickname($nickname): static
    {
        $this->nickname = $nickname;
        return $this;
    }

    /**
     * Get the full name of the user.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the full name of the user.
     *
     * @param string|null $name
     * @return static
     */
    public function setName($name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the email address of the user.
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set the email address of the user.
     *
     * @param string|null $email
     * @return static
     */
    public function setEmail($email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get the avatar / image URL for the user.
     *
     * @return string|null
     */
    public function getAvatar()
    {
        return $this->avatar;
    }

    /**
     * Set the avatar / image URL for the user.
     *
     * @param string|null $avatar
     * @return static
     */
    public function setAvatar($avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    /**
     * Get the raw user array.
     *
     * @return array
     */
    public function getRaw()
    {
        return $this->user;
    }

    /**
     * Get the access token for the user.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set the access token for the user.
     *
     * @param string $token
     * @return static
     */
    public function setToken($token): static
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Get the refresh token for the user.
     *
     * @return string|null
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * Set the refresh token for the user.
     *
     * @param string|null $refreshToken
     * @return static
     */
    public function setRefreshToken($refreshToken): static
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    /**
     * Get the expires in seconds for the access token.
     *
     * @return int|null
     */
    public function getExpiresIn()
    {
        return $this->expiresIn;
    }

    /**
     * Set the expires in seconds for the access token.
     *
     * @param int|null $expiresIn
     * @return static
     */
    public function setExpiresIn($expiresIn): static
    {
        $this->expiresIn = $expiresIn;
        return $this;
    }

    /**
     * Map the given array onto the user's properties.
     *
     * @param array $attributes
     * @return static
     */
    public function map(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }
}
