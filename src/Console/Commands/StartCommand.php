<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('serve')
            ->setDescription('Start the development server')
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Port to run server on', 8000)
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to run server on', 'localhost');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $port = $input->getOption('port');
        $host = $input->getOption('host');
        
        $this->io->success("Server started on http://{$host}:{$port}");
        $this->io->info("Press Ctrl+C to stop the server");
        
        $publicPath = $this->basePath . '/public';
        passthru("php -S {$host}:{$port} -t {$publicPath}");
        
        return Command::SUCCESS;
    }
}