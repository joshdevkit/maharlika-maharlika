<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Maharlika\Support\Str;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InertiaInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install:inertia')
            ->setDescription('Install SPA Inertia');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Inertia Installation');
        
        // Create InertiaServiceProvider
        $providerName = 'InertiaServiceProvider';
        $providerPath = app_path('Providers/') . $providerName . '.php';
        $providerDir = dirname($providerPath);

        if (!is_dir($providerDir)) {
            mkdir($providerDir, 0755, true);
        }

        if (file_exists($providerPath)) {
            $this->io->warning("InertiaServiceProvider already exists!");
        } else {
            $stubPath = Framework::stub('inertia.stub');

            if (!file_exists($stubPath)) {
                $this->io->error("Stub file missing: inertia.stub");
                return Command::FAILURE;
            }

            $stub = file_get_contents($stubPath);
            file_put_contents($providerPath, $stub);

            $this->io->success("InertiaServiceProvider created successfully!");
        }

        // Register provider in bootstrapper/providers.php
        $providersFilePath = base_path('bootstrapper/providers.php');

        if (!file_exists($providersFilePath)) {
            $this->io->error("providers.php file not found in bootstrapper directory");
            return Command::FAILURE;
        }

        $providersContent = file_get_contents($providersFilePath);
        $providerClass = 'App\Providers\InertiaServiceProvider::class';

        if (str_contains($providersContent, $providerClass)) {
            $this->io->warning("InertiaServiceProvider is already registered in providers.php");
        } else {
            $pattern = '/(\/\/\s*list more provider if neccessary\s*\n)(\];)/';
            
            if (preg_match($pattern, $providersContent)) {
                $replacement = "$1    {$providerClass},\n$2";
                $providersContent = preg_replace($pattern, $replacement, $providersContent);
                
                file_put_contents($providersFilePath, $providersContent);
                $this->io->success("InertiaServiceProvider registered in providers.php");
            } else {
                $this->io->error("Could not find the correct location to insert provider in providers.php");
                return Command::FAILURE;
            }
        }

        $this->io->success('Inertia installation completed successfully!');
        return Command::SUCCESS;
    }
}