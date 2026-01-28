<?php

namespace Maharlika\Translation;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\ServiceProviderInterface;

class TranslationServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $container;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function register(): void
    {
        $this->container->singleton('translator', function ($app) {
            $config = $app->get('config');
            
            $locale = $config->get('app.locale', 'en');
            $fallbackLocale = $config->get('app.fallback_locale', 'en');

            return new Translator($app, $locale, $fallbackLocale);
        });

        // Alias for easier access
        $this->container->alias('translator', Translator::class);
    }

    public function boot(): void
    {
        // Set the translator locale from the app
        $translator = $this->container->get('translator');
        $app = $this->container->get('app');
        $translator->setLocale($app->getLocale());
    }
}