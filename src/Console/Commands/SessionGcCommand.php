<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Database\DatabaseManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionGcCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('session:gc')
            ->setDescription('Clean up expired sessions');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $config = config('session');
        $driver = $config['driver'] ?? 'file';

        $output->writeln("<info>Running session garbage collection for driver: {$driver}</info>");

        if ($driver === 'database') {
            $this->cleanDatabaseSessions($output, $config);
        } elseif ($driver === 'file') {
            $this->cleanFileSessions($output, $config);
        } else {
            $output->writeln("<comment>Driver {$driver} does not require garbage collection.</comment>");
            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    /**
     * Clean up database sessions
     */
    protected function cleanDatabaseSessions(OutputInterface $output, array $config): void
    {
       
        $table = $config['table'] ?? 'sessions';
        $lifetime = $config['lifetime'] ?? 120;
        $expiration = time() - ($lifetime * 60);

        $deleted = app('db')->table($table)
            ->where('last_activity', '<', $expiration)
            ->delete();

        $output->writeln("<info>Deleted {$deleted} expired session(s) from database.</info>");
    }

    /**
     * Clean up file sessions
     */
    protected function cleanFileSessions(OutputInterface $output, array $config): void
    {
        $path = $config['files'] ?? storage_path('framework/sessions');
        $lifetime = $config['lifetime'] ?? 120;
        $expiration = time() - ($lifetime * 60);

        if (!is_dir($path)) {
            $output->writeln("<comment>Session directory does not exist: {$path}</comment>");
            return;
        }

        $deleted = 0;
        $files = new \FilesystemIterator($path);

        foreach ($files as $file) {
            if ($file->isFile() && $file->getMTime() < $expiration) {
                unlink($file->getPathname());
                $deleted++;
            }
        }

        $output->writeln("<info>Deleted {$deleted} expired session file(s).</info>");
    }
}