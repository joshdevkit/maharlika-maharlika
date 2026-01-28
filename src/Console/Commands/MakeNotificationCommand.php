<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Maharlika\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeNotificationCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:notification')
            ->setDescription('Create a new notification class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the notification');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (!str_ends_with($name, 'Notification')) {
            $name .= 'Notification';
        }

        $path = app_path('Notifications/') . $name . '.php';
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Notification already exists: {$name}");
            return Command::FAILURE;
        }

        $stubPath = Framework::stub('notification.stub');

        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: notification.stub");
            return Command::FAILURE;
        }

        $stub = file_get_contents($stubPath);
        $classContents = Str::replaceFirst('{{ class }}', $name, $stub);

        file_put_contents($path, $classContents);

        $this->io->success("Notification created: {$name}");
        return Command::SUCCESS;
    }
}

