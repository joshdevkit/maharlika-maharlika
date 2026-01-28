<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KeyGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('key:generate')
            ->setDescription('Generate a new application key')
            ->addOption('show', 's', InputOption::VALUE_NONE, 'Display the key instead of modifying files')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run when in production');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $showKey = $input->getOption('show');
        $force = $input->getOption('force');

        $envFile = $this->basePath . '/.env';

        if (!file_exists($envFile)) {
            $this->io->error(".env file not found at {$envFile}");
            $this->io->info("Please create a .env file first by copying .env.example");
            return Command::FAILURE;
        }

        $envContents = file_get_contents($envFile);

        if (preg_match('/^APP_KEY=(.+)$/m', $envContents, $matches)) {
            $existingKey = trim($matches[1]);
            if (!empty($existingKey) && !$force) {
                $this->io->warning("Application key already exists!");
                $this->io->info("Use --force or -f flag to overwrite the existing key");
                return Command::SUCCESS;
            }
        }

        $key = $this->generateKey();

        if (preg_match('/^APP_KEY=.*$/m', $envContents)) {
            $envContents = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $envContents);
        } else {
            $envContents = rtrim($envContents) . "\nAPP_KEY={$key}\n";
        }

        if (file_put_contents($envFile, $envContents) === false) {
            $this->io->error("Failed to write to .env file");
            return Command::FAILURE;
        }

        $this->io->success("Application key set successfully.");
        
        if ($showKey) {
            $this->io->info("Key: {$key}");
        }

        return Command::SUCCESS;
    }

    protected function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }
}