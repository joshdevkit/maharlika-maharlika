<?php

namespace Maharlika\Framework;

use Maharlika\Providers\ServiceProvider;

class ApiFrameworkProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register framework view namespace
        if (app()->has('view.finder')) {
            $viewFinder = app()->make('view.finder');
            
            $viewFinder->addNamespace('framework', [
                __DIR__ . '/resources/views',
            ]);
        }

        if (app()->has('view.component.resolver')) {
            $resolver = app()->make('view.component.resolver');
            $resolver->addAlias('framework', 'Maharlika\\Framework\\Components');
        }
    }
}