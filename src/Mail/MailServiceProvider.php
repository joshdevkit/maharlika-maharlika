<?php

namespace Maharlika\Mail;

use Maharlika\Providers\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mailer is already registered in Application
    }

    public function boot(): void
    {
        if (app()->has('view.finder')) {
            $viewFinder = app()->make('view.finder');
            
            $packageViewsPath = __DIR__ . '/resources/views';
            
            $viewFinder->addNamespace('mail', [
                app()->basePath('resources/views/vendor/mail'), 
                $packageViewsPath, 
            ]);
        }
    }
}