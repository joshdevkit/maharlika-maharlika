<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NotificationInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('notification:install')
            ->setDescription('Install the notifications table by creating a migration');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stubPath = Framework::stub('notifications-migration-file.stub');
        if (!file_exists($stubPath)) {
            $io->error("Stub file not found: {$stubPath}");
            return self::FAILURE;
        }

        // Check if a notifications migration already exists
        $migrationDir = app()->basePath('database/migrations');
        $existing = glob($migrationDir . '/*create_notifications_table.php');

        if (!empty($existing)) {
            $io->warning("A notifications migration already exists");
            return self::SUCCESS;
        }

        // Create new migration
        $timestamp = date('Ymd_His');
        $filename = "{$timestamp}_create_notifications_table.php";
        $finalFilepath = $migrationDir . '/' . $filename;

        copy($stubPath, $finalFilepath);

        $io->success("Migration created: " . str_replace('\\', '/', $finalFilepath));

        return self::SUCCESS;
    }
}
