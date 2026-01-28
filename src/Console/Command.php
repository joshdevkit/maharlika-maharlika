<?php

namespace Maharlika\Console;

use Maharlika\Contracts\ApplicationInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class Command extends SymfonyCommand
{
    protected ApplicationInterface $app;
    protected string $basePath;
    protected SymfonyStyle $io;

    public function __construct(ApplicationInterface $app, string $basePath, ?string $name = null)
    {
        $this->app = $app;
        $this->basePath = $basePath;
        
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        return $this->handle($input, $output);
    }

    abstract protected function handle(InputInterface $input, OutputInterface $output): int;

    protected function getMigrationsPath(): string
    {
        return $this->basePath . '/database/migrations';
    }
}
