<?php

namespace Maharlika\Database\FluentORM;

use Maharlika\Contracts\Support\Arrayable;
use Maharlika\Database\Capsule;
use Maharlika\Database\FluentORM\Builder;
use Maharlika\Exceptions\MassAssignmentException;
use Maharlika\Database\Collection;
use Maharlika\Facades\Log;
use Maharlika\Support\Str;

use function Maharlika\Support\enum_value;

abstract class Model implements \JsonSerializable, Arrayable
{
    use \Maharlika\Database\Traits\HasRelationships,
        \Maharlika\Database\Traits\HasAttributes,
        \Maharlika\Database\Traits\HasCasting,
        \Maharlika\Database\Traits\HasTimestamps,
        \Maharlika\Database\Traits\HidesAttributes,
        \Maharlika\Database\Traits\HasEvents,
        \Maharlika\Database\Traits\TracksChanges,
        \Maharlika\Database\Traits\GuardsAttributes;


    protected $table;
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $fillable = [];
    protected $guarded = ['*'];
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    protected $hidden = [];
    protected $visible = [];
    protected $casts = [];
    protected $dates = [];
    protected $appends = [];
    protected $with = [];
    public $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $perPage  = 15;
    protected $connection;

    protected static $lazyEagerLoading = false;
    protected static $globalWith = [];
    protected static $booted = [];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Enable eager loading hints in development
     */
    protected static $eagerLoadingHintsEnabled = false;

    /**
     * Track accessed relationships for N+1 detection
     */
    protected static $relationshipAccesses = [];

    /**
     * initialize observable arrays
     */
    protected $observables = [];

    /**
     * The callback that is responsible for handling discarded attribute violations.
     *
     * @var callable|null
     */
    protected static $discardedAttributeViolationCallback;

    /**
     * Indicates if an exception should be thrown instead of silently discarding non-fillable attributes.
     *
     * @var bool
     */
    protected static $modelsShouldPreventSilentlyDiscardingAttributes = false;

    /**
     * Indicates if accessing missing attributes should throw an exception.
     *
     * @var bool
     */
    protected static $modelsShouldPreventAccessingMissingAttributes = false;

    /**
     * Indicates if lazy loading should be prevented.
     *
     * @var bool
     */
    protected static $modelsShouldPreventLazyLoading = false;

    /**
     * Determine if discarding guarded attribute fills is disabled.
     *
     * @return bool
     */
    public static function preventsSilentlyDiscardingAttributes(): bool
    {
        return static::$modelsShouldPreventSilentlyDiscardingAttributes;
    }

    /**
     * Determine if accessing missing attributes is disabled.
     *
     * @return bool
     */
    public static function preventsAccessingMissingAttributes(): bool
    {
        return static::$modelsShouldPreventAccessingMissingAttributes;
    }

    /**
     * Determine if lazy loading is disabled.
     *
     * @return bool
     */
    public static function preventsLazyLoading(): bool
    {
        return static::$modelsShouldPreventLazyLoading;
    }

    /**
     * Enable strict mode for models (recommended for development).
     * This enables all protection mechanisms.
     *
     * @param  bool  $shouldBeStrict
     * @return void
     */
    public static function shouldBeStrict(bool $shouldBeStrict = true): void
    {
        static::preventLazyLoading($shouldBeStrict);
        static::preventSilentlyDiscardingAttributes($shouldBeStrict);
        static::preventAccessingMissingAttributes($shouldBeStrict);
    }

    /**
     * Prevent silently discarding non-fillable attributes during mass assignment.
     *
     * @param  bool  $prevent
     * @return void
     */
    public static function preventSilentlyDiscardingAttributes(bool $prevent = true): void
    {
        static::$modelsShouldPreventSilentlyDiscardingAttributes = $prevent;
    }

    /**
     * Prevent accessing missing attributes on the model.
     *
     * @param  bool  $prevent
     * @return void
     */
    public static function preventAccessingMissingAttributes(bool $prevent = true): void
    {
        static::$modelsShouldPreventAccessingMissingAttributes = $prevent;
    }

    /**
     * Prevent lazy loading relationships.
     *
     * @param  bool  $prevent
     * @return void
     */
    public static function preventLazyLoading(bool $prevent = false): void
    {
        static::$modelsShouldPreventLazyLoading = $prevent;
    }

    /**
     * Set the callback to handle discarded attribute violations.
     *
     * @param  callable|null  $callback
     * @return void
     */
    public static function handleDiscardedAttributeViolationUsing(?callable $callback): void
    {
        static::$discardedAttributeViolationCallback = $callback;
    }

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     */
    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            static::boot();
        }
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     */
    protected static function bootTraits(): void
    {
        $class = static::class;

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists($class, $method)) {
                forward_static_call([$class, $method]);
            }
        }
    }

    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param bool $halt
     * @return mixed
     */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        // Just fire the registered event callbacks, don't call the registration methods
        return static::fireEvent($event, $this);
    }

    /**
     * Register a creating model event callback.
     */
    protected static function creating(callable $callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a model event callback.
     */
    protected static function registerModelEvent(string $event, callable $callback): void
    {
        if (!isset(static::$booted[static::class . '_' . $event])) {
            static::$booted[static::class . '_' . $event] = [];
        }

        static::$booted[static::class . '_' . $event][] = $callback;
    }

    /**
     * Fire a model event.
     */
    protected static function fireEvent(string $event, $model): mixed
    {
        $key = static::class . '_' . $event;

        if (isset(static::$booted[$key])) {
            foreach (static::$booted[$key] as $callback) {
                $result = $callback($model);

                // If any callback returns false, stop and return false
                if ($result === false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Fill the model with an array of attributes.
     * Mimics Laravel's mass assignment protection.
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        $fillable = $this->fillableFromArray($attributes);
        foreach ($attributes as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded || static::preventsSilentlyDiscardingAttributes()) {
                if (isset(static::$discardedAttributeViolationCallback)) {
                    call_user_func(static::$discardedAttributeViolationCallback, $this, [$key]);
                } else {
                    throw new MassAssignmentException(sprintf(
                        'Add [%s] to fillable array property to allow mass assignment on [%s].',
                        $key,
                        get_class($this)
                    ));
                }
            }
        }

        if (
            count($attributes) !== count($fillable) &&
            static::preventsSilentlyDiscardingAttributes()
        ) {
            $keys = array_diff(array_keys($attributes), array_keys($fillable));

            if (isset(static::$discardedAttributeViolationCallback)) {
                call_user_func(static::$discardedAttributeViolationCallback, $this, $keys);
            } else {
                throw new MassAssignmentException(sprintf(
                    'Add fillable property [%s] to allow mass assignment on [%s].',
                    implode(', ', $keys),
                    get_class($this)
                ));
            }
        }

        return $this;
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        // Get class name without namespace
        $className = class_basename(static::class);

        // Convert to snake_case and pluralize
        return Str::plural(Str::snake($className));
    }


    public function setTable()
    {
        return  $this->table = $this->getTable();
    }

    public static function query()
    {
        return (new static)->newQuery();
    }

    protected static function newQuery()
    {
        $model = new static;

        // Set table if not already set
        if ($model->table === null) {
            $model->table = $model->getTable();
        }

        // Get the DatabaseManager instead of Connection
        $manager = Capsule::getManager();

        // Manager creates the QueryBuilder (connection is injected inside)
        $query = $manager->table($model->getTable(), $model);

        // Get the connection name from the query builder's connection
        $connectionName = $query->getConnection()->getName();
        if ($connectionName) {
            $model->setConnection($connectionName);
        }

        $builder = new Builder($query, $model);
        // Apply global eager loading if set
        if (isset(static::$globalWith[static::class])) {
            $builder->with(static::$globalWith[static::class]);
        }

        return $builder;
    }

    public static function all($columns = ['*'])
    {
        return static::query()->get(
            is_array($columns) ? $columns : func_get_args()
        );
    }

    public static function hydrate(array|Collection $items, Model $model): array
    {
        // Convert Collection to array if needed
        if ($items instanceof Collection) {
            $items = $items->all();
        }

        // Ensure table and connection are set on the model before cloning
        if ($model->table === null) {
            $model->table = $model->getTable();
        }

        $models = [];

        foreach ($items as $item) {
            // Convert stdClass to array if needed
            if ($item instanceof \stdClass) {
                $item = (array) $item;
            }

            // Clone the provided model instance
            $instance = clone $model;

            // Preserve connection from the original model
            $instance->connection = $model->connection;

            // Use unguarded to allow all attributes from database
            static::unguarded(function () use ($instance, $item) {
                $instance->fill($item);
            });

            $instance->exists = true;
            $instance->syncOriginal();

            $models[] = $instance;
        }

        return $models;
    }

    protected function insertGetId(array $attributes): int|string
    {
        $query = \Maharlika\Database\Capsule::table($this->getTable(), $this);

        $query->insert($attributes);

        if ($this->incrementing) {
            $id = $query->getConnection()->getPdo()->lastInsertId();
            return $this->keyType === 'int' ? (int) $id : $id;
        }

        // For non-incrementing keys, return the key from attributes
        if (!isset($attributes[$this->primaryKey])) {
            throw new \RuntimeException(
                "Primary key [{$this->primaryKey}] is not set for non-incrementing model. " .
                    "Make sure to set it manually or use a trait like HasUuid."
            );
        }

        return $attributes[$this->primaryKey];
    }



    /**
     * Save the model to the database.
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = [])
    {
        // Fire saving event (can return false to cancel)
        if (method_exists($this, 'fireModelEvent')) {
            $result = $this->fireModelEvent('saving');
            if ($result === false) {
                return false;
            }
        }

        // Fire creating/updating event for new/existing models
        if (!$this->exists) {
            if (method_exists($this, 'fireModelEvent')) {
                $result = $this->fireModelEvent('creating');
                if ($result === false) {
                    return false;
                }
            }
        } else {
            if (method_exists($this, 'fireModelEvent')) {
                $result = $this->fireModelEvent('updating');
                if ($result === false) {
                    return false;
                }
            }
        }

        // Add timestamps (unless disabled in options)
        if ($this->timestamps && ($options['timestamps'] ?? true)) {
            $this->updateTimestamps();
        }

        if ($this->exists) {
            // Update
            if (!$this->isDirty()) {
                // Still fire events and finish save even if nothing changed
                if ($options['force'] ?? false) {
                    $this->finishSave($options);
                }
                return true;
            }

            $id = $this->getAttribute($this->primaryKey);
            $dirty = $this->getDirty();

            $dirty = $this->castAttributesForDatabase($dirty);

            $saved = self::query()->where($this->primaryKey, $id)->update($dirty);

            if ($saved) {
                // Sync original (unless disabled in options)
                if ($options['syncOriginal'] ?? true) {
                    $this->syncOriginal();
                }

                // Fire updated event
                if (method_exists($this, 'fireModelEvent')) {
                    $this->fireModelEvent('updated', false);
                }

                // Finish save (track changes, fire saved event)
                $this->finishSave($options);
            }

            return $saved;
        }

        // Insert
        $attributesForDatabase = $this->castAttributesForDatabase($this->attributes);

        $id = $this->insertGetId($attributesForDatabase);

        // Set the primary key attribute if it's not already set
        if (!isset($this->attributes[$this->primaryKey])) {
            $this->attributes[$this->primaryKey] = $id;
        }

        // Force ID to be the first key
        $this->attributes = [$this->primaryKey => $this->attributes[$this->primaryKey]] + $this->attributes;
        $this->exists = true;

        // Mark as recently created
        if (method_exists($this, 'setWasRecentlyCreated')) {
            $this->setWasRecentlyCreated(true);
        }

        // Sync original (unless disabled in options)
        if ($options['syncOriginal'] ?? true) {
            $this->syncOriginal();
        }

        // Fire created event
        if (method_exists($this, 'fireModelEvent')) {
            $this->fireModelEvent('created', false);
        }

        // Finish save (track changes, fire saved event)
        $this->finishSave($options);

        return $saved = true;
    }



    public function update(array $attributes): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->fill($attributes);
        return $this->save();
    }

    /**
     * Delete the model from the database.
     *
     * @return bool
     */
    public function delete()
    {
        if (!$this->exists) {
            return false;
        }

        // Fire deleting event (can return false to cancel)
        if (method_exists($this, 'fireModelEvent')) {
            if ($this->fireModelEvent('deleting') === false) {
                return false;
            }
        }

        $id = $this->getAttribute($this->primaryKey);
        $query = Capsule::table($this->getTable());
        $deleted = $query->where($this->primaryKey, '=', $id)->delete() > 0;

        if ($deleted) {
            $this->exists = false;

            // Fire deleted event
            if (method_exists($this, 'fireModelEvent')) {
                $this->fireModelEvent('deleted', false);
            }
        }

        return $deleted;
    }

    public function newInstance(array $attributes, bool $exists = false): static
    {
        $model = new static;

        // When creating from database, bypass mass assignment protection
        if ($exists) {
            static::unguarded(function () use ($model, $attributes) {
                $model->fill($attributes);
            });
        } else {
            $model->fill($attributes);
        }

        $model->exists = $exists;

        if ($exists) {
            $model->syncOriginal();
        }

        return $model;
    }

    public function replicate(?array $except = null): static
    {
        $except = $except ?: [
            $this->primaryKey,
            static::CREATED_AT,
            static::UPDATED_AT,
        ];

        $attributes = array_diff_key($this->attributes, array_flip($except));

        return new static($attributes);
    }

    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    public function isDirty(string|array|null $attributes = null): bool
    {
        $dirty = $this->getDirty();

        if (is_null($attributes)) {
            return count($dirty) > 0;
        }

        $attributes = is_array($attributes) ? $attributes : func_get_args();

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key) {
            return $this->original[$key] ?? $default;
        }

        return $this->original;
    }

    public function toArray(): array
    {
        $attributes = $this->getArrayableAttributes();
        $relations = $this->getArrayableRelations();

        return array_merge($attributes, $relations);
    }


    /**
     * Get an array of all arrayable relations.
     */
    protected function getArrayableRelations(): array
    {
        return array_map(function ($relation) {
            if ($relation instanceof Collection) {
                return $relation->map(function ($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                })->all();
            }

            return $relation instanceof Model ? $relation->toArray() : $relation;
        }, $this->relations);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function getEntityAttributes(): array
    {
        return $this->fillable;
    }

    /**
     * Get the observable event names
     */
    public function getObservableEvents(): array
    {
        return array_merge(
            [
                'retrieved',
                'creating',
                'created',
                'updating',
                'updated',
                'saving',
                'saved',
                'deleting',
                'deleted',
                'restoring',
                'restored',
                'replicating',
            ],
            $this->observables
        );
    }


    public function getKey(): mixed
    {
        return $this->getAttribute($this->primaryKey);
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's route key.
     *
     * @return mixed
     */
    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return $this->getPrimaryKey();
    }

    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    public function setIncrementing(bool $value): static
    {
        $this->incrementing = $value;
        return $this;
    }

    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function setKeyType(string $type): static
    {
        $this->keyType = $type;
        return $this;
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     */
    public function setConnection(?string $name): static
    {
        $this->connection = $name;
        return $this;
    }

    /**
     * Get the database connection name.
     */
    public function getConnectionName()
    {
        return enum_value($this->connection);
    }

    /**
     * Get the number of models to return per page
     */
    public function setPerPage(int $perPage): static
    {
        $this->perPage = $perPage;
        return $this;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public static function enableLazyEagerLoading(): void
    {
        static::$lazyEagerLoading = true;
    }


    public static function globalWith(array $relations): void
    {
        static::$globalWith[static::class] = $relations;
    }

    /**
     * Enable eager loading hints
     */
    public static function enableEagerLoadingHints(): void
    {
        static::$eagerLoadingHintsEnabled = true;
    }

    /**
     * Disable eager loading hints
     */
    public static function disableEagerLoadingHints(): void
    {
        static::$eagerLoadingHintsEnabled = false;
    }

    /**
     * Check if eager loading hints are enabled
     */
    public static function hasEagerLoadingHintsEnabled(): bool
    {
        return static::$eagerLoadingHintsEnabled;
    }

    /**
     * Track relationship access for N+1 detection
     */
    protected function trackRelationshipAccess(string $relation): void
    {
        if (!static::$eagerLoadingHintsEnabled) {
            return;
        }

        $class = static::class;
        $key = $class . '::' . $relation;

        if (!isset(static::$relationshipAccesses[$key])) {
            static::$relationshipAccesses[$key] = [
                'count' => 0,
                'logged' => false
            ];
        }

        static::$relationshipAccesses[$key]['count']++;

        // Only log once when we detect the N+1 pattern (accessed more than once)
        // and only if it was lazy loaded (not from eager loaded relations)
        if (
            static::$relationshipAccesses[$key]['count'] > 1
            && !static::$relationshipAccesses[$key]['logged']
            && !array_key_exists($relation, $this->relations)
        ) {
            static::$relationshipAccesses[$key]['logged'] = true;

            Log::debug(
                sprintf(
                    '[N+1 Query Detected] Relationship [%s] on model [%s] was accessed %d times. ' .
                        'Consider eager loading with: %s::with(\'%s\')->get()',
                    $relation,
                    $class,
                    static::$relationshipAccesses[$key]['count'],
                    class_basename($class),
                    $relation
                )
            );
        }
    }


    /**
     * Reset relationship access tracking (call this at the beginning of each request)
     */
    public static function resetRelationshipAccessTracking(): void
    {
        static::$relationshipAccesses = [];
    }

    /**
     * Dynamically retrieve attributes or relationships.
     */
    public function __get(string $key)
    {
        if (static::preventsAccessingMissingAttributes()) {
            if (!$this->hasAttribute($key) && !method_exists($this, $key) && !isset($this->relations[$key])) {
                throw new \LogicException(sprintf(
                    'Attribute [%s] does not exist on [%s] model.',
                    $key,
                    get_class($this)
                ));
            }
        }

        if ($this->hasAttribute($key)) {
            return $this->getAttribute($key);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        return null;
    }


    /**
     * Eager load relations on the model.
     */
    public function load(string|array $relations): static
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        $query = static::query()->with($relations);

        // Set constraints to only get this model
        $query->where($this->primaryKey, $this->getKey());

        // Get the model with relations
        $model = $query->first();

        // Copy loaded relations to this instance
        if ($model) {
            foreach ($relations as $relation) {
                if (isset($model->relations[$relation])) {
                    $this->setRelation($relation, $model->relations[$relation]);
                }
            }
        }

        return $this;
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }


    public function __clone()
    {
        // Deep clone relations
        $this->relations = array_map(function ($relation) {
            if ($relation instanceof Collection) {
                return clone $relation;
            }
            if (is_object($relation)) {
                return clone $relation;
            }
            return $relation;
        }, $this->relations);
    }


    public static function __callStatic(string $method, array $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    public function __call(string $method, array $parameters)
    {
        $query = static::newQuery();

        $result = $query->$method(...$parameters);
        
        return $result;
    }
}
