<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Contracts\Http\RouterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RouteClearCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('route:clear')
            ->setDescription('Remove the route cache file');
    }
   
    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->info('Clearing route cache...');

        try {
            $router = $this->app->getContainer()->make(RouterInterface::class);
            
            if ($router->clearCache()) {
                $this->io->success('Route cache cleared successfully.');
                return self::SUCCESS;
            } else {
                $this->io->warning('No route cache found to clear.');
                return self::SUCCESS;
            }
        } catch (\Throwable $e) {
            $this->io->error('Failed to clear route cache: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}