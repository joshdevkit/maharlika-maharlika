<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseSeederCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:seed')
            ->setDescription('Seed the database with records')
            ->addOption('class', null, InputOption::VALUE_REQUIRED, 'The seeder class to run', 'DatabaseSeeder');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $seederClass = $input->getOption('class');

        if (!str_contains($seederClass, '\\')) {
            $seederClass = 'Database\\Seeder\\' . $seederClass;
        }

        if (!class_exists($seederClass)) {
            $this->io->error("Seeder class not found: {$seederClass}");
            return Command::FAILURE;
        }

        $this->io->info("Seeding [{$seederClass}]");

        try {
            $seeder = new $seederClass();

            if (!method_exists($seeder, 'run')) {
                $this->io->error("Seeder must have a run() method.");
                return Command::FAILURE;
            }

            $startTime = microtime(true);
            $seeder->run();
            $endTime = microtime(true);

            $duration = round($endTime - $startTime, 2);

            $this->io->newLine();
            $this->io->success("Database seeded successfully in {$duration}s");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->io->error("Seeding failed: {$e->getMessage()}");
            $this->io->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
