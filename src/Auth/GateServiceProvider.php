<?php

namespace Maharlika\Auth;

use Maharlika\Auth\Access\Gate as AccessGate;
use Maharlika\Contracts\Auth\Access\Gate;
use Maharlika\Providers\ServiceProvider;

class GateServiceProvider extends ServiceProvider
{
    /**
     * All of the container singletons that should be registered.
     *
     * @var array
     */
    public array $singletons = [
        'gate' => AccessGate::class,
    ];

    public function register(): void
    {
        $this->app->singleton('gate', function ($c) {
            return new AccessGate($c, function () use ($c) {
                return $c->get('auth')->user();
            });
        });

        // Alias both interface and concrete class to 'gate'
        $this->app->alias('gate', Gate::class);
        $this->app->alias('gate', AccessGate::class);
    }

    public function boot(): void
    {
        $this->discoverPolicies();
        $this->registerPolicies();
    }

    /**
     * Automatically discover and register all policies from app/Policies directory.
     */
    protected function discoverPolicies(): void
    {
        $policyDir = $this->basePath('app/Policies');

        if (!is_dir($policyDir)) {
            return;
        }

        $gate = $this->app->get('gate');
        $policies = $this->scanPoliciesDirectory($policyDir);

        foreach ($policies as $policyClass => $modelClass) {
            if (class_exists($policyClass) && class_exists($modelClass)) {
                $gate->policy($modelClass, $policyClass);
            }
        }
    }

    /**
     * Register policies defined in policies() method.
     * This allows manual policy registration to override auto-discovered policies.
     */
    protected function registerPolicies(): void
    {
        $gate = $this->app->get('gate');
        $policies = $this->policies();

        foreach ($policies as $modelClass => $policyClass) {
            if (class_exists($policyClass) && class_exists($modelClass)) {
                $gate->policy($modelClass, $policyClass);
            }
        }
    }

    /**
     * Get the policies defined in this provider.
     * Override this method in child providers to manually register policies.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [];
    }

    /**
     * Scan the policy directory and map policies to models.
     *
     * @param string $directory
     * @return array<class-string, class-string>
     */
    protected function scanPoliciesDirectory(string $directory): array
    {
        $policies = [];
        $files = $this->getAllPhpFiles($directory);

        foreach ($files as $file) {
            $policyClass = $this->getClassFromFile($file);
            
            if (!$policyClass) {
                continue;
            }

            // Try to infer the model from the policy name
            $modelClass = $this->inferModelFromPolicy($policyClass);
            
            if ($modelClass) {
                $policies[$policyClass] = $modelClass;
            }
        }

        return $policies;
    }

    /**
     * Get all PHP files recursively from a directory.
     *
     * @param string $directory
     * @return array
     */
    protected function getAllPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Get the fully qualified class name from a file.
     *
     * @param string $file
     * @return string|null
     */
    protected function getClassFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        $namespace = '';
        $class = '';

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $class = trim($matches[1]);
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return null;
    }

    /**
     * Infer the model class from the policy class name.
     *
     * @param string $policyClass
     * @return string|null
     */
    protected function inferModelFromPolicy(string $policyClass): ?string
    {
        // Extract the base class name
        $className = class_basename($policyClass);

        // Remove "Policy" suffix
        if (!str_ends_with($className, 'Policy')) {
            return null;
        }

        $modelName = substr($className, 0, -6); // Remove "Policy"
        
        if (empty($modelName)) {
            return null;
        }

        // Try common model locations
        $possiblePaths = [
            'App\\Models\\' . $modelName,
        ];

        foreach ($possiblePaths as $path) {
            if (class_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Get the model class from a policy by inspecting its methods.
     * This is a fallback if naming convention doesn't work.
     *
     * @param string $policyClass
     * @return string|null
     */
    protected function getModelFromPolicyMethods(string $policyClass): ?string
    {
        if (!class_exists($policyClass)) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass($policyClass);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $parameters = $method->getParameters();
                
                // Look for methods with at least 2 parameters (User, Model)
                if (count($parameters) < 2) {
                    continue;
                }

                $secondParam = $parameters[1];
                $type = $secondParam->getType();

                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $modelClass = $type->getName();
                    
                    if (class_exists($modelClass)) {
                        return $modelClass;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            return null;
        }

        return null;
    }

    /**
     * Register policy namespaces for auto-discovery.
     * Allows packages to register their own policy directories.
     *
     * @param array $namespaces [namespace => directory_path]
     */
    public function registerPolicyNamespaces(array $namespaces): void
    {
        $gate = $this->app->get('gate');

        foreach ($namespaces as $namespace => $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $policies = $this->scanPoliciesDirectory($directory);

            foreach ($policies as $policyClass => $modelClass) {
                if (class_exists($policyClass) && class_exists($modelClass)) {
                    $gate->policy($modelClass, $policyClass);
                }
            }
        }
    }
}