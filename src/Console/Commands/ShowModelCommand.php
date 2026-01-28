<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use ReflectionClass;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class ShowModelCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('show:model')
            ->setDescription('Display detailed information about a model')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the model');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $modelClass = "App\\Models\\{$name}";

        if (!class_exists($modelClass)) {
            $this->io->error("Model not found: {$modelClass}");
            return Command::FAILURE;
        }

        $model = new $modelClass();
        $reflection = new ReflectionClass($modelClass);
        
        // Get terminal width
        $terminal = new Terminal();
        $terminalWidth = $terminal->getWidth();

        // Header
        $this->outputSectionHeader($output, "Model: {$name}", $terminalWidth);
        $this->io->newLine();

        // Basic Info
        $this->outputSubsection($output, 'Basic Info', $terminalWidth);
        $this->outputKeyValue($output, 'Table', $model->getTable() ?? 'N/A', $terminalWidth);
        $this->outputKeyValue($output, 'Primary Key', $model->getKeyName() ?? 'id', $terminalWidth);
        $this->outputKeyValue($output, 'Incrementing', $model->getIncrementing() ? 'Yes' : 'No', $terminalWidth);
        $this->outputKeyValue($output, 'Timestamps', $model->usesTimestamps() ? 'Yes' : 'No', $terminalWidth);
        $this->outputKeyValue($output, 'Connection', $model->getConnectionName() ?? 'default', $terminalWidth);
        $this->io->newLine();

        // Fillable
        $fillable = $model->getFillable();
        if (!empty($fillable)) {
            $this->outputSubsection($output, 'Fillable', $terminalWidth);
            foreach ($fillable as $field) {
                $output->writeln("  <info>•</info> {$field}");
            }
            $this->io->newLine();
        }

        // Guarded
        $guarded = $model->getGuarded();
        if (!empty($guarded)) {
            $this->outputSubsection($output, 'Guarded', $terminalWidth);
            foreach ($guarded as $field) {
                $output->writeln("  <comment>•</comment> {$field}");
            }
            $this->io->newLine();
        }

        // Casts
        $casts = $model->getCasts();
        if (!empty($casts)) {
            $this->outputSubsection($output, 'Casts', $terminalWidth);
            foreach ($casts as $key => $cast) {
                $this->outputKeyValue($output, $key, $cast, $terminalWidth, '  ');
            }
            $this->io->newLine();
        }

        // Hidden
        $hidden = $model->getHidden();
        if (!empty($hidden)) {
            $this->outputSubsection($output, 'Hidden Attributes', $terminalWidth);
            foreach ($hidden as $field) {
                $output->writeln("  <comment>•</comment> {$field}");
            }
            $this->io->newLine();
        }

        // Traits
        $traits = array_keys($reflection->getTraits());
        if (!empty($traits)) {
            $this->outputSubsection($output, 'Traits', $terminalWidth);
            foreach ($traits as $trait) {
                $shortTrait = class_basename($trait);
                $output->writeln("  <info>•</info> {$shortTrait}");
            }
            $this->io->newLine();
        }

        // Relations
        $this->outputSubsection($output, 'Relations', $terminalWidth);
        $relations = $this->getModelRelations($model, $reflection);

        if (empty($relations)) {
            $output->writeln("  <comment>No relations detected</comment>");
        } else {
            foreach ($relations as $rel => $info) {
                $this->outputKeyValue($output, $rel, $info, $terminalWidth, '  ');
            }
        }
        $this->io->newLine();

        // Default Attributes
        if (method_exists($model, 'getAttributes')) {
            $attrs = $model->getAttributes();
            if (!empty($attrs)) {
                $this->outputSubsection($output, 'Default Attributes', $terminalWidth);
                foreach ($attrs as $key => $value) {
                    $displayValue = is_string($value) ? $value : json_encode($value);
                    $this->outputKeyValue($output, $key, $displayValue, $terminalWidth, '  ');
                }
                $this->io->newLine();
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Output a section header with full-width line
     */
    private function outputSectionHeader(OutputInterface $output, string $title, int $terminalWidth): void
    {
        $titleLength = strlen($title);
        $lineLength = $terminalWidth - 2;
        
        $output->writeln('<info>' . str_repeat('═', $lineLength) . '</info>');
        $output->writeln('<info>' . $title . '</info>');
        $output->writeln('<info>' . str_repeat('═', $lineLength) . '</info>');
    }

    /**
     * Output a subsection header
     */
    private function outputSubsection(OutputInterface $output, string $title, int $terminalWidth): void
    {
        $output->writeln("<info>{$title}:</info>");
    }

    /**
     * Output a key-value pair with dots
     */
    private function outputKeyValue(
        OutputInterface $output, 
        string $key, 
        string $value, 
        int $terminalWidth,
        string $indent = ''
    ): void {
        $indentLength = strlen($indent);
        $keyLength = strlen($key);
        $valueLength = strlen($value);
        
        // Calculate dots needed
        $dotsLength = $terminalWidth - $indentLength - $keyLength - $valueLength - 4; // -4 for spacing
        $dotsLength = max(2, $dotsLength);
        
        $dots = str_repeat('.', $dotsLength);
        
        $output->writeln("{$indent}{$key} {$dots} {$value}");
    }

    /**
     * Detect relation methods by checking if calling them returns a Relation instance.
     */
    protected function getModelRelations($model, ReflectionClass $reflection): array
    {
        $relations = [];

        foreach ($reflection->getMethods() as $method) {
            if (
                $method->class === get_class($model) &&
                $method->getNumberOfParameters() === 0 &&
                $method->isPublic()
            ) {
                try {
                    $result = $method->invoke($model);

                    if ($result instanceof \Maharlika\Database\Relations\Relation) {
                        $relations[$method->getName()] = class_basename($result);
                    }
                } catch (\Throwable $e) {
                    // ignore methods that fail
                }
            }
        }

        return $relations;
    }
}