<?php

namespace Maharlika\Console\Commands;

use Maharlika\Console\Command;
use Maharlika\Contracts\Http\RouterInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RouteListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('route:list')
            ->setDescription('List all registered routes')
            ->addOption('method', 'm', InputOption::VALUE_OPTIONAL, 'Filter by HTTP method')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Filter by route name')
            ->addOption('uri', 'u', InputOption::VALUE_OPTIONAL, 'Filter by URI')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        try {
            $router = $this->app->getContainer()->make(RouterInterface::class);
            $router->discoverRoutes();
            
            $routes = $this->applyFilters($router->getRoutes(), $input);
            
            if (empty($routes)) {
                $this->io->warning('No routes found.');
                return self::SUCCESS;
            }
            
            $input->getOption('json') 
                ? $this->outputJson($routes, $output)
                : $this->outputTable($routes);
            
            return self::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->io->error('Failed to list routes: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
    
    private function applyFilters(array $routes, InputInterface $input): array
    {
        if ($method = $input->getOption('method')) {
            $routes = array_filter($routes, fn($r) => $r['method'] === strtoupper($method));
        }
        
        if ($name = $input->getOption('name')) {
            $routes = array_filter($routes, fn($r) => 
                isset($r['name']) && str_contains(strtolower($r['name']), strtolower($name))
            );
        }
        
        if ($uri = $input->getOption('uri')) {
            $routes = array_filter($routes, fn($r) => 
                str_contains(strtolower($r['uri']), strtolower($uri))
            );
        }
        
        return array_values($routes);
    }
    
    private function outputTable(array $routes): void
    {
        $this->io->title('Application Routes');
        $this->io->text('Total: <info>' . count($routes) . '</info>');
        $this->io->newLine();
        
        $table = new Table($this->io);
        $table->setHeaders(['Method', 'URI', 'Name', 'Action']);
        
        foreach ($routes as $route) {
            $table->addRow([
                $this->colorMethod($route['method']),
                $route['uri'],
                $route['name'] ?? '-',
                $this->formatAction($route['action']),
            ]);
        }
        
        $table->render();
    }
    
    private function outputJson(array $routes, OutputInterface $output): void
    {
        $formatted = array_map(fn($r) => [
            'method' => $r['method'],
            'uri' => $r['uri'],
            'name' => $r['name'] ?? null,
            'action' => $this->getActionString($r['action']),
        ], $routes);
        
        $output->writeln(json_encode($formatted, JSON_PRETTY_PRINT));
    }
    
    private function colorMethod(string $method): string
    {
        return match($method) {
            'GET' => "<info>{$method}</info>",
            'POST' => "<comment>{$method}</comment>",
            'PUT' => "<question>{$method}</question>",
            'DELETE' => "<error>{$method}</error>",
            'PATCH' => "<fg=cyan>{$method}</>",
            default => $method,
        };
    }
    
    private function formatAction(mixed $action): string
    {
        if ($action instanceof \Closure) {
            return '<fg=yellow>Closure</>';
        }
        
        $actionStr = $this->getActionString($action);
        
        if (str_contains($actionStr, '@')) {
            [$controller, $method] = explode('@', $actionStr, 2);
            $short = basename(str_replace('\\', '/', $controller));
            return "<fg=green>{$short}@{$method}</>";
        }
        
        return $actionStr;
    }
    
    private function getActionString(mixed $action): string
    {
        if ($action instanceof \Closure) {
            return 'Closure';
        }
        
        if (is_array($action) && count($action) === 2) {
            return "{$action[0]}@{$action[1]}";
        }
        
        return is_string($action) ? $action : 'Unknown';
    }
}