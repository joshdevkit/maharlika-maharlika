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
            ->setHelp($this->getHelpText());
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $this->normalizePolicyName($input->getArgument('name'));
        $model = $input->getOption('model');
        $force = $input->getOption('force');
        
        // If no model provided, create a plain policy
        $isPlain = empty($model);

        $policyDir = $this->ensurePolicyDirectory($io);
        $filepath = $policyDir . '/' . $name . '.php';

        if ($this->shouldAbort($filepath, $force, $name, $io)) {
            return self::FAILURE;
        }

        $content = $this->generatePolicyContent($name, $model, $isPlain, $io);
        
        if ($content === null) {
            return self::FAILURE;
        }

        file_put_contents($filepath, $content);

        $io->success("Policy created successfully: app/Policies/{$name}.php");

        $this->displayNextSteps($io, $model, $isPlain);

        return self::SUCCESS;
    }

    /**
     * Ensure policy name ends with "Policy".
     */
    protected function normalizePolicyName(string $name): string
    {
        return str_ends_with($name, 'Policy') ? $name : $name . 'Policy';
    }

    /**
     * Ensure the policy directory exists.
     */
    protected function ensurePolicyDirectory(SymfonyStyle $io): string
    {
        $policyDir = app()->basePath('app/Policies');
        
        if (!is_dir($policyDir)) {
            mkdir($policyDir, 0755, true);
            $io->note("Created directory: app/Policies");
        }

        return $policyDir;
    }

    /**
     * Check if file exists and should abort.
     */
    protected function shouldAbort(string $filepath, bool $force, string $name, SymfonyStyle $io): bool
    {
        if (file_exists($filepath) && !$force) {
            $io->error("Policy already exists: {$name}");
            $io->note("Use --force to overwrite");
            return true;
        }

        return false;
    }

    /**
     * Generate the policy content from stub.
     */
    protected function generatePolicyContent(string $name, ?string $model, bool $isPlain, SymfonyStyle $io): ?string
    {
        $stubFilename = $isPlain ? 'policy-plain.stub' : 'policy.stub';
        $stubPath = Framework::stub($stubFilename);
        
        if (!file_exists($stubPath)) {
            $io->error("Stub file not found: {$stubPath}");
            return null;
        }

        $stub = file_get_contents($stubPath);

        if ($isPlain) {
            return $this->replacePlainPlaceholders($stub, $name);
        }

        return $this->replaceModelPlaceholders($stub, $name, $model);
    }

    /**
     * Replace placeholders for plain policy.
     */
    protected function replacePlainPlaceholders(string $stub, string $name): string
    {
        return str_replace(
            ['{{ namespace }}', '{{ class }}'],
            ['App\\Policies', $name],
            $stub
        );
    }

    /**
     * Replace placeholders for model-based policy.
     */
    protected function replaceModelPlaceholders(string $stub, string $name, string $model): string
    {
        $modelName = $model ?: str_replace('Policy', '', $name);
        $modelNamespace = 'App\\Models\\' . $modelName;
        $modelVariable = lcfirst($modelName);

        return str_replace(
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

    /**
     * Display next steps for the user.
     */
    protected function displayNextSteps(SymfonyStyle $io, ?string $model, bool $isPlain): void
    {
        if (!$isPlain && $model) {
            $io->section('Next Steps');
            $io->text([
                "Register your policy in app/Providers/AuthorizationServiceProvider.php:",
                "Register the provider into bootstrapper/providers.php",
            ]);
        }
    }

    /**
     * Get the help text for the command.
     */
    protected function getHelpText(): string
    {
        return <<<'HELP'
The <info>make:policy</info> command creates a new policy class.

<comment>Usage:</comment>
  <info>php maharlika make:policy UserPolicy</info>
  <info>php maharlika make:policy PostPolicy --model=Post</info>
  <info>php maharlika make:policy AdminPolicy</info>

<comment>Options:</comment>
  <info>-m, --model=MODEL</info>    Specify the model that the policy applies to.
                          If provided, creates a policy with CRUD methods.
                          If omitted, creates a plain policy with just a before() method.

  <info>-f, --force</info>          Overwrite the policy if it already exists.

<comment>Examples:</comment>
  # Create a plain policy (no model specified)
  <info>php maharlika make:policy AdminPolicy</info>

  # Create a model-based policy with CRUD methods
  <info>php maharlika make:policy PostPolicy --model=Post</info>

  # Create a policy and overwrite if exists
  <info>php maharlika make:policy UserPolicy -m User --force</info>

<comment>Note:</comment>
  - Policy names will automatically have "Policy" appended if not provided
  - Plain policies include a before() method for authorization hooks
  - Model-based policies include viewAny, view, create, update, delete, restore, and forceDelete methods
HELP;
    }
}