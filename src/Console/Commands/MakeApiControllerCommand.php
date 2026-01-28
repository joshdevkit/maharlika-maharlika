<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeApiControllerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:api')
            ->setDescription('Create a new RESTful API controller class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the api controller');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $controllerInput = $input->getArgument('name');

        // Ask if prefix should be added
        $addPrefix = $this->io->ask(
            "Would you like to add a prefix? (y/n)",
            "n"
        );

        $prefix = "";
        if (strtolower($addPrefix) === "y") {
            $prefix = $this->io->ask(
                "Enter prefix (example: users, auth, v1/products)",
                ""
            );
        }

        // Ask about model binding
        $useModel = $this->io->ask(
            "Would you like to use a model? (y/n)",
            "n"
        );

        $modelName = null;
        $modelExists = false;
        $modelVariable = null;

        if (strtolower($useModel) === "y") {
            $modelName = $this->io->ask(
                "Enter the model name (e.g., User, Product)",
                ""
            );

            if (!empty($modelName)) {
                // Check if model exists
                $modelPath = app_path("Models/{$modelName}.php");
                $modelExists = file_exists($modelPath);

                if (!$modelExists) {
                    $this->io->warning("Model '{$modelName}' does not exist at: {$modelPath}");
                    $createModel = $this->io->ask(
                        "Would you like to create the model? (y/n)",
                        "n"
                    );

                    if (strtolower($createModel) === "y") {
                        // You can call your make:model command here
                        $this->io->note("Please run: php maharlika make:model {$modelName}");
                    }
                } else {
                    $this->io->success("Model '{$modelName}' found!");
                }

                // Generate model variable name (e.g., User -> $user, Product -> $product)
                $modelVariable = '$' . lcfirst($modelName);
            }
        }

        $pathParts = explode('/', $controllerInput);
        $className = array_pop($pathParts);

        $folderPath = implode('/', $pathParts);
        $namespacePath = implode('\\', $pathParts);

        $directory = app_path('Controllers/Api' . ($folderPath ? '/' . $folderPath : ''));
        $directory = str_replace('\\', '/', $directory);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filePath = "{$directory}/{$className}.php";

        if (file_exists($filePath)) {
            $this->io->error("Api Controller already exists: {$filePath}");
            return Command::FAILURE;
        }

        $namespace = 'App\\Controllers\\Api' . ($namespacePath ? '\\' . $namespacePath : '');

        $stub = $this->getStub();
        if ($stub === null) {
            return Command::FAILURE;
        }

        // Format prefix
        $prefix = trim($prefix, "/");
        $routePrefix = $prefix ? "api/{$prefix}" : "api";

        // Prepare model-specific replacements
        $modelImport = $modelName && $modelExists ? "\nuse App\\Models\\{$modelName};" : "";
        
        // Generate method bodies based on whether model is used
        if ($modelName && $modelExists) {
            $indexBody = "return {$modelName}::all();";
            $storeBody = "//";
            $showParameter = "{$modelName} {$modelVariable}";
            $showBody = "return {$modelVariable};";
            $updateParameter = "{$modelName} {$modelVariable}";
            $updateBody = "//";
            $destroyParameter = "{$modelName} {$modelVariable}";
            $destroyBody = "{$modelVariable}->delete();\n        return response()->json(['message' => 'Deleted successfully']);";
        } else {
            $indexBody = "//";
            $storeBody = "//";
            $showParameter = "\$id";
            $showBody = "//";
            $updateParameter = "\$id";
            $updateBody = "//";
            $destroyParameter = "\$id";
            $destroyBody = "//";
        }

        // Replace placeholders
        $stub = str_replace(
            [
                '{{ namespace }}',
                '{{ class }}',
                '{{ prefix }}',
                '{{ model_import }}',
                '{{ index_body }}',
                '{{ store_body }}',
                '{{ show_parameter }}',
                '{{ show_body }}',
                '{{ update_parameter }}',
                '{{ update_body }}',
                '{{ destroy_parameter }}',
                '{{ destroy_body }}'
            ],
            [
                $namespace,
                $className,
                $routePrefix,
                $modelImport,
                $indexBody,
                $storeBody,
                $showParameter,
                $showBody,
                $updateParameter,
                $updateBody,
                $destroyParameter,
                $destroyBody
            ],
            $stub
        );

        file_put_contents($filePath, $stub);

        $this->io->success("RESTful API Controller created: {$filePath}");
        $this->displayResourceInfo($output, $modelName, $modelExists);
        
        return Command::SUCCESS;
    }

    protected function getStub(): ?string
    {
        $stubPath = Framework::stub('api-controller.stub');

        if (!file_exists($stubPath)) {
            $this->io->error("Stub file missing: api-controller.stub");
            return null;
        }

        return file_get_contents($stubPath);
    }

    protected function displayResourceInfo(OutputInterface $output, ?string $modelName, bool $modelExists): void
    {
        $this->io->writeln('');
        $this->io->writeln('<info>RESTful methods generated:</info>');
        $this->io->writeln('  - index()   : GET    /resource');
        $this->io->writeln('  - store()   : POST   /resource');
        $this->io->writeln('  - show()    : GET    /resource/{id}');
        $this->io->writeln('  - update()  : PUT    /resource/{id}');
        $this->io->writeln('  - destroy() : DELETE /resource/{id}');
        
        if ($modelName && $modelExists) {
            $this->io->writeln('');
            $this->io->writeln("<comment>Route Model Binding enabled with model: {$modelName}</comment>");
        }
    }
}