<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakeEnumCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:enum')
            ->setDescription('Create a new enum class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the enum')
            ->addOption('string', 's', InputOption::VALUE_NONE, 'Create a string-backed enum')
            ->addOption('int', 'i', InputOption::VALUE_NONE, 'Create an int-backed enum');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $enumInput = $input->getArgument('name');

        // Parse path and class name
        $pathParts = explode('/', $enumInput);
        $className = array_pop($pathParts);

        // Ensure it ends with "Enum" convention
        if (!str_ends_with($className, 'Enum')) {
            $className .= 'Enum';
        }

        $folderPath = implode('/', $pathParts);
        $namespacePath = implode('\\', $pathParts);

        // Create directory path
        $directory = app_path('Enums' . ($folderPath ? '/' . $folderPath : ''));
        $directory = str_replace('\\', '/', $directory);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filePath = "{$directory}/{$className}.php";

        if (file_exists($filePath)) {
            $this->io->error("Enum already exists: {$filePath}");
            return Command::FAILURE;
        }

        // Build namespace
        $namespace = 'App\\Enums' . ($namespacePath ? '\\' . $namespacePath : '');

        // Determine enum type
        $isString = $input->getOption('string');
        $isInt = $input->getOption('int');
        
        $enumType = 'pure'; // default
        if ($isString) {
            $enumType = 'string';
        } elseif ($isInt) {
            $enumType = 'int';
        }

        // Get appropriate stub
        $stub = $this->getStub($enumType);
        if ($stub === null) {
            return Command::FAILURE;
        }

        // Replace placeholders
        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $className],
            $stub
        );

        // Write file
        file_put_contents($filePath, $stub);

        $this->io->success("Enum created: {$filePath}");
        
        // Show type info
        $typeMessage = match($enumType) {
            'string' => 'String-backed enum created',
            'int' => 'Int-backed enum created',
            default => 'Pure enum created'
        };
        $this->io->note($typeMessage);
        
        return Command::SUCCESS;
    }

    protected function getStub(string $type = 'pure'): ?string
    {
        $stubName = match($type) {
            'string' => 'enum.string.stub',
            'int' => 'enum.int.stub',
            default => 'enum.stub'
        };
        
        $stubPath = Framework::stub($stubName);

        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: {$stubName}");
            return null;
        }

        return file_get_contents($stubPath);
    }
}