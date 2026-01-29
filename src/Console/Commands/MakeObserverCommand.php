<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakeObserverCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:observer')
            ->setDescription('Create a new observer class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the observer')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'The model that the observer applies to');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $observerName = $this->parseObserverName($input->getArgument('name'));
        $modelName = $this->determineModelName($input->getOption('model'), $observerName['class']);
        
        $filePath = $this->buildFilePath($observerName);
        
        if (file_exists($filePath)) {
            $this->io->error("Observer already exists: {$filePath}");
            return Command::FAILURE;
        }

        $this->createObserverFile($filePath, $observerName, $modelName);
        
        $this->io->success("Observer created: {$filePath}");
        $this->displayNextSteps($modelName['name'], $observerName['class']);

        return Command::SUCCESS;
    }

    protected function parseObserverName(string $input): array
    {
        $pathParts = explode('/', $input);
        $className = array_pop($pathParts);

        // Ensure it ends with "Observer"
        if (!str_ends_with($className, 'Observer')) {
            $className .= 'Observer';
        }

        return [
            'class' => $className,
            'folder' => implode('/', $pathParts),
            'namespace' => implode('\\', $pathParts),
        ];
    }

    protected function determineModelName(?string $option, string $observerClass): array
    {
        $modelName = $option ?: $this->inferModelFromObserver($observerClass);
        
        if (!$modelName) {
            return $this->getGenericModel();
        }

        $modelPath = app_path("Models/{$modelName}.php");
        
        if (file_exists($modelPath)) {
            $this->io->success("Model '{$modelName}' found! Observer will be type-hinted.");
            return [
                'name' => $modelName,
                'class' => $modelName,
                'import' => "use App\\Models\\{$modelName};",
            ];
        }

        $this->io->warning("Model '{$modelName}' not found at: {$modelPath}");
        $this->io->note("Observer will be created with generic Model type-hint.");
        
        return $this->getGenericModel();
    }

    protected function inferModelFromObserver(string $observerClass): ?string
    {
        if (str_ends_with($observerClass, 'Observer')) {
            return substr($observerClass, 0, -8); // Remove "Observer"
        }
        
        return null;
    }

    protected function getGenericModel(): array
    {
        return [
            'name' => null,
            'class' => 'Model',
            'import' => "use Maharlika\\Database\\FluentORM\\Model;",
        ];
    }

    protected function buildFilePath(array $observerName): string
    {
        $directory = app_path('Observers' . ($observerName['folder'] ? '/' . $observerName['folder'] : ''));
        $directory = str_replace('\\', '/', $directory);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return "{$directory}/{$observerName['class']}.php";
    }

    protected function createObserverFile(string $filePath, array $observerName, array $modelName): void
    {
        $stub = $this->getStub();
        
        $namespace = 'App\\Observers' . ($observerName['namespace'] ? '\\' . $observerName['namespace'] : '');

        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ modelNamespace }}', '{{ model }}'],
            [$namespace, $observerName['class'], $modelName['import'], $modelName['class']],
            $stub
        );

        file_put_contents($filePath, $content);
    }

    protected function getStub(): string
    {
        $stubPath = Framework::stub('observer.stub');

        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: observer.stub");
            exit(Command::FAILURE);
        }

        return file_get_contents($stubPath);
    }

    protected function displayNextSteps(?string $modelName, string $observerClass): void
    {
        if (!$modelName) {
            return;
        }

        $this->io->section('Next Steps');
        
        $this->io->writeln([
            '',
            '<info>To register this observer, add the attribute to your model:</info>',
            '',
            '  <comment>use Maharlika\Database\Attributes\Observer;</comment>',
            '  <comment>use App\Observers\\' . $observerClass . ';</comment>',
            '',
            '  <comment>#[Observer(' . $observerClass . '::class)]</comment>',
            '  <comment>class ' . $modelName . ' extends Model</comment>',
            '  <comment>{</comment>',
            '      <comment>// ...</comment>',
            '  <comment>}</comment>',
            '',
        ]);
    }
}