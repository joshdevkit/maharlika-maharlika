<?php

namespace Maharlika\Pagination;

use Maharlika\Providers\ServiceProvider;

class PaginationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }


    public function boot(): void
    {
       if (app()->has('view.finder')) {
            $viewFinder = app()->make('view.finder');
            
            $packageViewsPath = __DIR__ . '/resources/views/components';
            
            $viewFinder->addNamespace('pagination', [
                app()->basePath('resources/views/vendor/pagination'),
                $packageViewsPath,
            ]);
        }
    }
}