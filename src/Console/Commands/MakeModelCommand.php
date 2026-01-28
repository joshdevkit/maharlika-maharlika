<?php

// ============================================================================
// MakeModelCommand.php
// ============================================================================

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Database\Schema\MigrationCreator;
use Maharlika\Support\Framework;
use Maharlika\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeModelCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:model')
            ->setDescription('Create a new model class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the model')
            ->addOption('migration', 'm', InputOption::VALUE_NONE, 'Create a migration file');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $withMigration = $input->getOption('migration');

        $filename = $this->createModel($name);

        if ($filename) {
            $this->io->success("Created model: {$filename}");
        } else {
            return Command::FAILURE;
        }

        if ($withMigration) {
            $creator = new MigrationCreator($this->getMigrationsPath());

            $table = Str::plural(Str::snake($name));
            $migrationName = "create_{$table}_table";

            $migrationFile = $creator->create($migrationName);
            $this->io->writeln("Created migration: {$migrationFile}");
        }

        return Command::SUCCESS;
    }

    protected function createModel(string $name): ?string
    {
        $path = base_path("app/Models");
        $path = str_replace('\\', '/', $path);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filename = "{$path}/{$name}.php";

        if (file_exists($filename)) {
            $this->io->warning("Skipping Model '{$name}' already exists.");
            return null;
        }

        $stub = $this->getStub($name);
        if ($stub === null) {
            return null;
        }

        file_put_contents($filename, $stub);

        return $filename;
    }

    protected function getStub(string $name): ?string
    {
        $stubPath = Framework::stub('model.stub');

        $stub = file_get_contents($stubPath);

        return Str::replaceFirst('{{ class }}', $name, $stub);
    }
}
