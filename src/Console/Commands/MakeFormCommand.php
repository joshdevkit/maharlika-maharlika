<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Maharlika\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeFormCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:form')
            ->setDescription('Create a new form request class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the form request');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (!str_ends_with($name, 'Request')) {
            $name .= 'Request';
        }

        $path = app_path('Forms/') . $name . '.php';
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Form already exists: {$name}");
            return Command::FAILURE;
        }

        $stubPath = Framework::stub('form-request.stub');
        
        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: form-request.stub");
            return Command::FAILURE;
        }

        $stub = file_get_contents($stubPath);

        $classContents = Str::replaceFirst(
            '{{ class }}',
            $name,
            $stub
        );

        file_put_contents($path, $classContents);

        $this->io->success("Form created: {$name}");
        return Command::SUCCESS;
    }
}
