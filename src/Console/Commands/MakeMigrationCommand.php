<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Database\Schema\MigrationCreator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:migration')
            ->setDescription('Create a new migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $creator = new MigrationCreator($this->getMigrationsPath());

        if ($creator->exists($name)) {
            $this->io->error("Migration already exists: {$name}");
            return Command::FAILURE;
        }

        $filename = $creator->create($name);
        $finalFilepath = app()->basePath('database/migrations/' . $filename);
        $finalFilepath = str_replace('\\', '/', $finalFilepath);

        $this->io->success("Created migration: {$finalFilepath}");
        return Command::SUCCESS;
    }
}
