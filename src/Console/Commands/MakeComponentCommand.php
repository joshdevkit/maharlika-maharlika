<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Support\Framework;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeComponentCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:component')
            ->setDescription('Create a new view component')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the component');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $className = ucfirst($name);
        $viewName = strtolower(preg_replace('/([A-Z])/', '-$1', $className));
        $viewName = ltrim($viewName, '-');

        $classPath = $this->basePath . "/app/View/Components/{$className}.php";
        $viewPath = $this->basePath . "/resources/views/components/{$viewName}.blade.php";

        // Create directories if they don't exist
        if (!is_dir(dirname($classPath))) {
            mkdir(dirname($classPath), 0755, true);
        }

        if (!is_dir(dirname($viewPath))) {
            mkdir(dirname($viewPath), 0755, true);
        }

        // ------------------------------
        // Load stubs from external files
        // ------------------------------
        $classStubPath = Framework::stub('component.stub');
        $bladeStubPath = Framework::stub('component.blade.stub');

        if (!file_exists($classStubPath) || !file_exists($bladeStubPath)) {
            $this->io->error("Stub files not found.");
            return Command::FAILURE;
        }

        $classStub = file_get_contents($classStubPath);
        $bladeStub = file_get_contents($bladeStubPath);

        $classStub = str_replace(
            ['{{className}}', '{{viewName}}'],
            [$className, $viewName],
            $classStub
        );

        $bladeStub = str_replace(
            ['{{viewName}}'],
            [$viewName],
            $bladeStub
        );

        file_put_contents($classPath, $classStub);
        $this->io->info("Component class created: {$classPath}");

        file_put_contents($viewPath, $bladeStub);
        $this->io->info("Component view created: {$viewPath}");

        return Command::SUCCESS;
    }
}
