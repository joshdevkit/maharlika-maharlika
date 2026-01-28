<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class CacheClearCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cache:clear')
            ->setDescription('Clear all application cache files');
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->io->info('Clearing application cache...');

        try {
            $cachePath = storage_path('framework/cache');

            if (! is_dir($cachePath)) {
                $this->io->info('Cache directory does not exist.');
                return self::SUCCESS;
            }

            $this->clearDirectory($cachePath);

            $this->io->success('Application cache cleared successfully.');
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->io->error('Failed to clear cache: ' . $e->getMessage());
            $this->io->error('Stack trace: ' . $e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Delete all files and folders inside a directory.
     */
    protected function clearDirectory(string $path): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
    }
}
