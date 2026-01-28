<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeAuthCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install:auth')
            ->setDescription('Scaffold basic authentication (login, register, logout)');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Installation started: Authentication Scaffolding......');

        try {
            $this->createDirectories();
            $this->createControllers();
            $this->createViews();
            $this->createLayouts();
            
            $this->io->success('Authentication scaffolding created successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error("Installation failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Create necessary directories
     */
    protected function createDirectories(): void
    {
        $directories = [
            'resources/views/layouts',
            'resources/views/auth',
            'app/Controllers/Auth',
        ];

        foreach ($directories as $directory) {
            $path = base_path($directory);
            
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Create authentication controllers
     */
    protected function createControllers(): void
    {
        $controllers = [
            'LoginController' => 'scaffolding/auth/LoginController.stub',
            'RegisterController' => 'scaffolding/auth/RegisterController.stub',
            'LogoutController' => 'scaffolding/auth/LogoutController.stub',
        ];

        foreach ($controllers as $name => $stubFile) {
            $path = base_path("app/Controllers/Auth/{$name}.php");
            
            if (file_exists($path)) {
                $this->io->writeln("  <comment>!!!</comment> Skipping {$name} ......");
                continue;
            }
            
            $stub = $this->getStub($stubFile);
            file_put_contents($path, $stub);
        }

        $dashboardPath = base_path('app/Controllers/Auth/DashboardController.php');
        if (!file_exists($dashboardPath)) {
            $stub = $this->getStub('scaffolding/auth/DashboardController.stub');
            file_put_contents($dashboardPath, $stub);
        }
    }

    /**
     * Create authentication views
     */
    protected function createViews(): void
    {
        $views = [
            'login.blade.php' => 'scaffolding/auth/views/login.stub',
            'register.blade.php' => 'scaffolding/auth/views/register.stub',
            'layout.blade.php' => 'scaffolding/auth/views/layout.stub',
        ];

        foreach ($views as $name => $stubFile) {
            $path = base_path("resources/views/auth/{$name}");
            
            if (file_exists($path)) {
                $this->io->writeln("  <comment>⚠</comment> {$name} already exists, skipping...");
                continue;
            }
            
            $stub = $this->getStub($stubFile);
            file_put_contents($path, $stub);
        }

        $dashboardPath = base_path('resources/views/dashboard.blade.php');
        if (!file_exists($dashboardPath)) {
            $stub = $this->getStub('scaffolding/auth/views/dashboard.stub');
            file_put_contents($dashboardPath, $stub);
        }
    }

    /**
     * Create app layout
     */
    protected function createLayouts(): void
    {
        $layoutPath = base_path('resources/views/layouts/app.blade.php');
        
        if (file_exists($layoutPath)) {
            $this->io->writeln("  <comment>⚠</comment> app.blade.php already exists, skipping...");
            return;
        }
        
        $stub = $this->getStub('scaffolding/auth/views/app-layout.stub');
        file_put_contents($layoutPath, $stub);
    }

    /**
     * Get stub content from file
     */
    protected function getStub(string $stub): string
    {
        $stubPath = Framework::stub($stub);

        if (file_exists($stubPath)) {
            return file_get_contents($stubPath);
        }

        throw new \RuntimeException("Stub file not found: {$stub}");
    }
}