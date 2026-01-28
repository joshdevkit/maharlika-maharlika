<?php

namespace Maharlika\Database\Factory;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class Seeder
{
    protected SymfonyStyle $io;

    public function __construct()
    {
        $input = new ArgvInput();
        $output = new ConsoleOutput();
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Run the database seeds.
     */
    abstract public function run(): void;

    /**
     * Call another seeder class.
     */
    protected function call(string|array $seederClasses): void
    {
        $classes = is_array($seederClasses) ? $seederClasses : [$seederClasses];

        foreach ($classes as $class) {
            $className = class_basename($class);

            if (!class_exists($class)) {
                $this->io->error("Seeder class not found: {$class}");
                continue;
            }

            $this->io->text("Seeding <info>{$className}</info>...");

            $seeder = new $class();

            if (!method_exists($seeder, 'run')) {
                $this->io->error("Seeder {$className} must have a run() method.");
                continue;
            }

            $startTime = microtime(true);
            $seeder->run();
            $endTime = microtime(true);

            $duration = round($endTime - $startTime, 2);
            $this->io->success("Completed {$className} ({$duration}s)");
        }
    }

    /**
     * Output helper methods for convenience (wrappers for SymfonyStyle)
     */
    protected function info(string $message): void
    {
        $this->io->info($message);
    }

    protected function success(string $message): void
    {
        $this->io->success($message);
    }

    protected function error(string $message): void
    {
        $this->io->error($message);
    }

    protected function warning(string $message): void
    {
        $this->io->warning($message);
    }

    protected function line(string $message = ''): void
    {
        $this->io->writeln($message);
    }
}
