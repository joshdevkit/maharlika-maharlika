<?php

namespace Maharlika\Auth\Access;

use Closure;
use InvalidArgumentException;
use Maharlika\Contracts\Auth\Access\Gate as GateContract;
use Maharlika\Contracts\Container\ContainerInterface;


class Gate implements GateContract
{
    use HandlesAuthorization;
    /**
     * All of the defined abilities.
     */
    protected array $abilities = [];

    /**
     * All of the defined policies.
     */
    protected array $policies = [];

    /**
     * All of the registered before callbacks.
     */
    protected array $beforeCallbacks = [];

    /**
     * All of the registered after callbacks.
     */
    protected array $afterCallbacks = [];

    /**
     * Create a new gate instance.
     */
    public function __construct(
        protected ContainerInterface $container,
        protected Closure $userResolver,
        protected array $abilities_default = [],
        protected array $policies_default = [],
        protected array $beforeCallbacks_default = [],
        protected array $afterCallbacks_default = []
    ) {
        $this->abilities = $abilities_default;
        $this->policies = $policies_default;
        $this->beforeCallbacks = $beforeCallbacks_default;
        $this->afterCallbacks = $afterCallbacks_default;
    }

    /**
     * Determine if a given ability has been defined.
     */
    public function has($ability): bool
    {
        $ability = is_object($ability) ? $ability->value : $ability;

        return isset($this->abilities[$ability]);
    }

    /**
     * Define a new ability.
     */
    public function define($ability, $callback): static
    {
        $ability = is_object($ability) ? $ability->value : $ability;

        if (is_string($callback) && str_contains($callback, '@')) {
            $callback = $this->buildAbilityCallback($callback);
        }

        $this->abilities[$ability] = $callback;

        return $this;
    }

    /**
     * Define abilities for a resource.
     */
    public function resource($name, $class, ?array $abilities = null): static
    {
        $abilities = $abilities ?? [
            'viewAny' => 'viewAny',
            'view' => 'view',
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
        ];

        foreach ($abilities as $ability => $method) {
            $this->define("{$name}.{$ability}", "{$class}@{$method}");
        }

        return $this;
    }

    /**
     * Define a policy class for a given class type.
     */
    public function policy($class, $policy): static
    {
        $this->policies[$class] = $policy;

        return $this;
    }

    /**
     * Register a callback to run before all Gate checks.
     */
    public function before(callable $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to run after all Gate checks.
     */
    public function after(callable $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Determine if all of the given abilities should be granted for the current user.
     */
    public function allows($ability, $arguments = []): bool
    {
        return $this->check($ability, $arguments);
    }

    /**
     * Determine if any of the given abilities should be denied for the current user.
     */
    public function denies($ability, $arguments = []): bool
    {
        return !$this->allows($ability, $arguments);
    }

    /**
     * Determine if all of the given abilities should be granted for the current user.
     */
    public function check($abilities, $arguments = []): bool
    {
        return collect($this->normalizeAbilities($abilities))->every(
            fn($ability) => $this->inspect($ability, $arguments)->allowed()
        );
    }

    /**
     * Determine if any one of the given abilities should be granted for the current user.
     */
    public function any($abilities, $arguments = []): bool
    {
        return collect($this->normalizeAbilities($abilities))->contains(
            fn($ability) => $this->inspect($ability, $arguments)->allowed()
        );
    }

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @throws AuthorizationException
     */
    public function authorize($ability, $arguments = []): Response
    {
        return $this->inspect($ability, $arguments)->authorize();
    }

    /**
     * Inspect the user for the given ability.
     */
    public function inspect($ability, $arguments = []): Response
    {
        try {
            $result = $this->raw($ability, $arguments);

            if ($result instanceof Response) {
                return $result;
            }

            return $result ? Response::allow() : Response::deny();
        } catch (AuthorizationException $e) {
            return $e->toResponse();
        }
    }

    /**
     * Get the raw result from the authorization callback.
     *
     * @throws AuthorizationException
     */
    public function raw($ability, $arguments = []): mixed
    {
        $ability = is_object($ability) ? $ability->value : $ability;
        $arguments = is_array($arguments) ? $arguments : [$arguments];

        $user = $this->resolveUser();

        // Run before callbacks
        $result = $this->callBeforeCallbacks($user, $ability, $arguments);

        if (!is_null($result)) {
            return $result;
        }

        // Check if there's a policy for the first argument
        if (!empty($arguments)) {
            $result = $this->callPolicyMethod($user, $ability, $arguments);

            if (!is_null($result)) {
                return $result;
            }
        }

        // Check for ability definition
        if (isset($this->abilities[$ability])) {
            $result = $this->callAbilityCallback($user, $ability, $arguments);
        } else {
            $result = false;
        }

        // Run after callbacks
        return $this->callAfterCallbacks($user, $ability, $arguments, $result);
    }

    /**
     * Resolve the user from the resolver.
     */
    protected function resolveUser(): mixed
    {
        return call_user_func($this->userResolver);
    }

    /**
     * Get a policy instance for a given class.
     */
    public function getPolicyFor($class): mixed
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (!isset($this->policies[$class])) {
            throw new InvalidArgumentException("Policy not defined for [{$class}].");
        }

        return $this->resolvePolicy($this->policies[$class]);
    }

    /**
     * Build a policy class instance for the given policy.
     */
    protected function resolvePolicy($class): mixed
    {
        return $this->container->make($class);
    }

    /**
     * Get a gate instance for the given user.
     */
    public function forUser($user): static
    {
        return new static(
            $this->container,
            fn() => $user,
            $this->abilities,
            $this->policies,
            $this->beforeCallbacks,
            $this->afterCallbacks
        );
    }

    /**
     * Get all of the defined abilities.
     */
    public function abilities(): array
    {
        return $this->abilities;
    }

    /**
     * Call all of the before callbacks and return if a result is given.
     */
    protected function callBeforeCallbacks($user, string $ability, array $arguments): mixed
    {
        foreach ($this->beforeCallbacks as $before) {
            $result = $before($user, $ability, $arguments);

            if (!is_null($result)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Call all of the after callbacks with the given result.
     */
    protected function callAfterCallbacks($user, string $ability, array $arguments, $result): mixed
    {
        foreach ($this->afterCallbacks as $after) {
            $afterResult = $after($user, $ability, $arguments, $result);

            if (!is_null($afterResult)) {
                return $afterResult;
            }
        }

        return $result;
    }

    /**
     * Call the appropriate method on a policy.
     */
    protected function callPolicyMethod($user, string $ability, array $arguments): mixed
    {
        $instance = $arguments[0];

        try {
            $policy = $this->getPolicyFor($instance);
        } catch (InvalidArgumentException $e) {
            return null;
        }

        if (!method_exists($policy, $ability)) {
            return null;
        }

        return $policy->{$ability}($user, ...$arguments);
    }

    /**
     * Call the ability callback.
     */
    protected function callAbilityCallback($user, string $ability, array $arguments): mixed
    {
        $callback = $this->abilities[$ability];

        return $callback($user, ...$arguments);
    }

    /**
     * Build an ability callback that calls a method on a class.
     */
    protected function buildAbilityCallback(string $callback): Closure
    {
        [$class, $method] = explode('@', $callback);

        return function ($user, ...$arguments) use ($class, $method) {
            $instance = $this->container->make($class);

            return $instance->{$method}($user, ...$arguments);
        };
    }

    /**
     * Normalize abilities into an array.
     */
    protected function normalizeAbilities($abilities): array
    {
        if (is_string($abilities) || is_object($abilities)) {
            return [$abilities];
        }

        return $abilities;
    }

   
}