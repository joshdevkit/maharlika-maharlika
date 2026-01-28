<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Maharlika\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMailCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:mail')
            ->setDescription('Create a new mail class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the mail class');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (!str_ends_with($name, 'Mail')) {
            $name .= 'Mail';
        }

        $path = app_path('Mail/') . $name . '.php';
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Mail class already exists: {$name}");
            return Command::FAILURE;
        }

        $stubPath = Framework::stub('mail.stub');

        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: mail.stub");
            return Command::FAILURE;
        }

        $stub = file_get_contents($stubPath);

        $classContents = Str::replaceFirst(
            '{{ class }}',
            $name,
            $stub
        );

        file_put_contents($path, $classContents);

        $this->io->success("Mail created: {$name}");
        return Command::SUCCESS;
    }
}

