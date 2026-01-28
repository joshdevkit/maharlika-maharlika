<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StorageLinkCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('storage:link')
            ->setDescription('Create symbolic link for storage');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $links = config('storage.links', []);
        $created = false;

        foreach ($links as $link => $target) {
            // Normalize path separators for the current OS
            $link = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $link);
            $target = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target);

            // Ensure target directory exists
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
                $this->io->info("Created directory: {$target}");
            }

            // Check if link already exists
            if (file_exists($link) || is_link($link)) {
                $this->io->error("The [{$link}] link already exists.");
                continue;
            }

            // Create the symbolic link
            if ($this->createSymbolicLink($target, $link)) {
                $this->io->success("The [{$link}] link has been connected to [{$target}].");
                $created = true;
            } else {
                $this->io->error("Failed to create symbolic link from [{$link}] to [{$target}].");

                if (PHP_OS_FAMILY === 'Windows') {
                    $this->io->note("Please enable Developer Mode in Windows Settings to create symlinks without admin privileges.");
                    $this->io->note("Or run this command as Administrator.");
                }

                return Command::FAILURE;
            }
        }

        if ($created) {
            $this->io->success('The links have been created.');
        }

        return Command::SUCCESS;
    }

    /**
     * Create a symbolic link using the best method for the OS
     */
    private function createSymbolicLink(string $target, string $link): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->createWindowsSymlink($target, $link);
        }

        // Unix/Linux/Mac
        return @symlink($target, $link);
    }

    /**
     * Create a symbolic link on Windows without requiring admin privileges
     */
    private function createWindowsSymlink(string $target, string $link): bool
    {
        $target = str_replace('/', '\\', $target);
        $link = str_replace('/', '\\', $link);

        // Make paths absolute if they aren't already
        if (!$this->isAbsolutePath($target)) {
            $target = app()->basePath($target);
        }
        if (!$this->isAbsolutePath($link)) {
            $link = app()->basePath($link);
        }

        // Try native symlink first (works in Developer Mode on Windows 10+)
        if (@symlink($target, $link)) {
            return true;
        }

        // Fallback: Try junction for directories (doesn't require admin)
        if (is_dir($target)) {
            $output = [];
            $return = 0;
            exec("mklink /J \"{$link}\" \"{$target}\" 2>&1", $output, $return);

            if ($return === 0) {
                return true;
            }
        }

        // Last resort: Try directory symlink with mklink /D
        $output = [];
        $return = 0;
        exec("mklink /D \"{$link}\" \"{$target}\" 2>&1", $output, $return);

        return $return === 0;
    }

    /**
     * Check if a path is absolute
     */
    private function isAbsolutePath(string $path): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return preg_match('/^[A-Z]:\\\\/i', $path) === 1;
        }

        return strpos($path, '/') === 0;
    }
}
