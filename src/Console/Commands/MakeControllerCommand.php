<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeControllerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:controller')
            ->setDescription('Create a new controller class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the controller');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $controllerInput = $input->getArgument('name');

        $pathParts = explode('/', $controllerInput);
        $className = array_pop($pathParts);

        $folderPath = implode('/', $pathParts);
        $namespacePath = implode('\\', $pathParts);

        $directory = app_path('Controllers' . ($folderPath ? '/' . $folderPath : ''));
        $directory = str_replace('\\', '/', $directory);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filePath = "{$directory}/{$className}.php";

        if (file_exists($filePath)) {
            $this->io->error("Controller already exists: {$filePath}");
            return Command::FAILURE;
        }

        $namespace = 'App\\Controllers' . ($namespacePath ? '\\' . $namespacePath : '');

        $stub = $this->getStub();
        if ($stub === null) {
            return Command::FAILURE;
        }

        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $className],
            $stub
        );

        file_put_contents($filePath, $stub);

        $this->io->success("Controller created: {$filePath}");
        return Command::SUCCESS;
    }

    protected function getStub(): ?string
    {
        $stubPath = Framework::stub('controller.stub');

        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: controller.stub");
            return null;
        }

        return file_get_contents($stubPath);
    }
}