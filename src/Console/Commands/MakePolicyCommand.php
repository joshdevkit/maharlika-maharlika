<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakePolicyCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:policy')
            ->setDescription('Create a new policy class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the policy class')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'The model that the policy applies to')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite the policy if it already exists')
            ->addOption('plain', 'p', InputOption::VALUE_NONE, 'Create a plain policy without predefined methods');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $model = $input->getOption('model');
        $force = $input->getOption('force');
        $plain = $input->getOption('plain');

        // Ensure name ends with "Policy"
        if (!str_ends_with($name, 'Policy')) {
            $name .= 'Policy';
        }

        // Determine the policy directory
        $policyDir = app()->basePath('app/Policies');
        if (!is_dir($policyDir)) {
            mkdir($policyDir, 0755, true);
            $io->note("Created directory: app/Policies");
        }

        // Build the full path
        $filepath = $policyDir . '/' . $name . '.php';

        // Check if file exists
        if (file_exists($filepath) && !$force) {
            $io->error("Policy already exists: {$name}");
            $io->note("Use --force to overwrite");
            return self::FAILURE;
        }

        // Get the stub
        $stubFilename = $plain ? 'policy-plain.stub' : 'policy.stub';
        $stubPath = Framework::stub($stubFilename);
            
        if (!file_exists($stubPath)) {
            $io->error("Stub file not found: {$stubPath}");
            return self::FAILURE;
        }

        $stub = file_get_contents($stubPath);

        // For plain policies, only replace namespace and class
        if ($plain) {
            $content = str_replace(
                [
                    '{{ namespace }}',
                    '{{ class }}',
                ],
                [
                    'App\\Policies',
                    $name,
                ],
                $stub
            );
        } else {
            // Determine model information
            $modelName = $model;
            $modelNamespace = 'App\\Models\\' . $modelName;
            $modelVariable = $this->getModelVariable($modelName);

            // If no model specified, try to infer from policy name
            if (!$model) {
                $modelName = str_replace('Policy', '', $name);
                $modelNamespace = 'App\\Models\\' . $modelName;
                $modelVariable = $this->getModelVariable($modelName);
            }

            // Replace placeholders
            $content = str_replace(
                [
                    '{{ namespace }}',
                    '{{ class }}',
                    '{{ model }}',
                    '{{ modelNamespace }}',
                    '{{ modelVariable }}',
                ],
                [
                    'App\\Policies',
                    $name,
                    $modelName,
                    $modelNamespace,
                    $modelVariable,
                ],
                $stub
            );
        }

        // Write the file
        file_put_contents($filepath, $content);

        $io->success("Policy created successfully: app/Policies/{$name}.php");

        // Provide registration instructions (only for non-plain policies with model)
        if (!$plain && $model) {
            $modelName = $model;
            $io->section('Next Steps');
            $io->text([
                "Register your policy in app/Providers/AuthorizationServiceProvider.php:",
                "Register the provider into bootstrapper/providers.php",
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Convert model name to variable name.
     */
    protected function getModelVariable(string $modelName): string
    {
        return lcfirst($modelName);
    }
}