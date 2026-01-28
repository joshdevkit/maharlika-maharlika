<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeFactoryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:factory')
            ->setDescription('Create a new model factory')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the factory');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (!str_ends_with($name, 'Factory')) {
            $name .= 'Factory';
        }

        $modelName = str_replace('Factory', '', $name);

        $path = base_path("database/factories/{$name}.php");

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Factory already exists: {$name}");
            return Command::FAILURE;
        }

        $stub = $this->getStub();
        if ($stub === null) {
            return Command::FAILURE;
        }

        $stub = str_replace(
            ['{{ class }}', '{{ model }}'],
            [$name, $modelName],
            $stub
        );

        if (file_put_contents($path, $stub) === false) {
            $this->io->error("Failed to create factory: {$name}");
            return Command::FAILURE;
        }

        $this->io->success("Created factory: {$name}");
        $this->io->info("Location: database/factories/{$name}.php");

        return Command::SUCCESS;
    }

    protected function getStub(): ?string
    {
        $stubPath = Framework::stub('factory.stub');

        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: factory.stub");
            return null;
        }

        return file_get_contents($stubPath);
    }
}
