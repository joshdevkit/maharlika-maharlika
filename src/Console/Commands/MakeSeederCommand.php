<?php

// ============================================================================
// MakeSeederCommand.php
// ============================================================================

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeSeederCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:seeder')
            ->setDescription('Create a new seeder class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the seeder');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = base_path("database/seeder/{$name}.php");
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Seeder already exists: {$name}");
            return Command::FAILURE;
        }

        $stub = $this->getStub();
        if ($stub === null) {
            return Command::FAILURE;
        }

        $stub = str_replace('{{ class }}', $name, $stub);

        file_put_contents($path, $stub);

        $this->io->success("Created seeder: {$name}");
        return Command::SUCCESS;
    }

    protected function getStub(): ?string
    {
        $stubPath = Framework::stub('seeder.stub');

        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: seeder.stub");
            return null;
        }

        return file_get_contents($stubPath);
    }
}
