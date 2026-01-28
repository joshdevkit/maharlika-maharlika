<?php

namespace Maharlika\Console;

use Maharlika\Contracts\ApplicationInterface;
use Exception;
use Symfony\Component\Console\Application as SymfonyConsole;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

class Application
{
    protected ApplicationInterface $app;
    protected string $basePath;
    protected SymfonyConsole $console;

    public function __construct(ApplicationInterface $app, string $basePath)
    {
        $this->app = $app;
        $this->basePath = $basePath;
        $this->console = new SymfonyConsole('Framework CLI', '1.0.0');
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        $commandsPath = __DIR__ . '/Commands';
        
        if (!is_dir($commandsPath)) {
            return;
        }

        $commands = [];
        
        $files = glob($commandsPath . '/*.php');
        
        foreach ($files as $file) {
            $className = 'Maharlika\\Console\\Commands\\' . basename($file, '.php');
            
            // Check if class exists and is a valid command
            if (class_exists($className) && is_subclass_of($className, Command::class)) {
                try {
                    $commands[] = new $className($this->app, $this->basePath);
                } catch (\Throwable $e) {
                    throw new Exception($e->getMessage());
                }
            }
        }

        $this->console->addCommands($commands);
    }

    public function run(array $argv): int
    {
        $input = new \Symfony\Component\Console\Input\ArgvInput($argv);
        $output = new \Symfony\Component\Console\Output\ConsoleOutput();

        if (isset($argv[1]) && !in_array($argv[1], ['list', 'help', '--help', '-h'])) {
            if (!$this->verifyDatabase($input, $output)) {
                return 1;
            }
        }

        return $this->console->run($input, $output);
    }

    protected function verifyDatabase($input, $output): bool
    {
        try {
            $this->app->getContainer()->get('db');
            return true;
        } catch (\Exception $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error("Connection not set.");
            $io->writeln($e->getMessage());
            return false;
        }
    }
}