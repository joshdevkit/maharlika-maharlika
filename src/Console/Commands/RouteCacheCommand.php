<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Contracts\Http\RouterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RouteCacheCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('route:cache')
            ->setDescription('Create a route cache file for faster route registration');
    }
   
    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->info('Caching routes...');

        try {
            $router = $this->app->getContainer()->make(RouterInterface::class);
            
            $router->discoverRoutes();
            
            if ($router->cache()) {
                $routes = $router->getRoutes();
                $count = count($routes);

                $this->io->success("Successfully cached {$count} routes.");
                return self::SUCCESS;
            } else {
                $this->io->error('Failed to write cache file. Check storage permissions.');
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->io->error('Failed to cache routes: ' . $e->getMessage());
            $this->io->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}