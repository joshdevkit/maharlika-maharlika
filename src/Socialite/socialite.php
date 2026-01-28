<?php

if (!function_exists('socialite')) {
    /**
     * Get the Socialite manager instance.
     *
     * @param string|null $driver
     * @return \Maharlika\Socialite\SocialiteManager|\Maharlika\Contracts\Socialite\ProviderInterface
     */
    function socialite(?string $driver = null)
    {
        $manager = app('socialite');
        
        if ($driver === null) {
            return $manager;
        }
        
        return $manager->driver($driver);
    }
}