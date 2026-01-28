<?php

namespace Maharlika\Database\FluentORM;

use Maharlika\Database\Query\Builder as Query;
use Maharlika\Database\Relations\Relation;
use Maharlika\Database\Collection;
use Exception;
use Maharlika\Database\FluentORM\Model;
use Maharlika\Pagination\Paginator;

class Builder
{
    protected Query $query;
    protected Model $model;
    protected array $eagerLoad = [];

    public function __construct(Query $query, Model $model)
    {
        $this->query = $query;
        $this->model = $model;

        // Automatically set up eager loading from model's $with property
        $modelWith = $model->getWith();
        if (!empty($modelWith)) {
            $this->with($modelWith);
        }
    }

    /**
     * Get the model instance.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the underlying query builder.
     */
    public function getQuery(): Query
    {
        return $this->query;
    }

    /**
     * Create a new model instance and save it to the database.
     */
    public function create(array $attributes): Model
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->save();
        });
    }

    /**
     * Create a new instance of the model being queried.
     */
    public function newModelInstance(array $attributes = []): Model
    {
        $instance = $this->model->newInstance($attributes);
        // Maintain the connection name from the query
        if ($connectionName = $this->model->getConnectionName()) {
            $instance->setConnection($connectionName);
        }

        return $instance;
    }

    /**
     * Create a new model instance and save it to the database, bypassing mass assignment.
     */
    public function forceCreate(array $attributes): Model
    {
        return $this->model->unguarded(function () use ($attributes) {
            return $this->create($attributes);
        });
    }

    /**
     * Update or create a record matching the attributes, and fill it with values.
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $instance = $this->firstOrNew($attributes);

        $instance->fill($values)->save();

        return $instance;
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     */
    public function firstOrNew(array $attributes = [], array $values = []): Model
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->newModelInstance(array_merge($attributes, $values));
    }

    /**
     * Get the first record matching the attributes or create it.
     */
    public function firstOrCreate(array $attributes = [], array $values = []): Model
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->create(array_merge($attributes, $values));
    }

    /**
     * Set the relationships that should be eager loaded.
     */
    public function with(string|array $relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $eagerLoad = $this->parseWithRelations($relations);

        // Validate each relation immediately
        foreach (array_keys($eagerLoad) as $relation) {
            // For nested relations, only validate the first segment
            $relationName = explode('.', $relation)[0];

            // Try to call the method and check if it returns a Relation
            try {
                $result = Relation::noConstraints(function () use ($relationName) {
                    return $this->getModel()->$relationName();
                });

                if (!$result instanceof Relation) {
                    throw new Exception(sprintf(
                        'Call to undefined relationship [%s] on model [%s].',
                        $relationName,
                        get_class($this->getModel())
                    ));
                }
            } catch (\Throwable $e) {
                if ($e instanceof Exception && str_contains($e->getMessage(), 'undefined relationship')) {
                    throw $e;
                }

                throw new Exception(sprintf(
                    'Call to undefined relationship [%s] on model [%s].',
                    $relationName,
                    get_class($this->getModel())
                ));
            }
        }

        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        return $this;
    }

    /**
     * Prevent the specified relations from being eager loaded.
     */
    public function without(string|array $relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        foreach ($relations as $relation) {
            unset($this->eagerLoad[$relation]);
        }

        return $this;
    }

    /**
     * Parse a list of relations into individuals.
     */
    protected function parseWithRelations(array $relations): array
    {
        $results = [];

        foreach ($relations as $name => $constraints) {

            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            if (is_string($name) && str_contains($name, ':')) {
                [$name, $columns] = $this->createSelectWithConstraint($name);

                // If there are already constraints, merge them
                if ($constraints && is_callable($constraints)) {
                    $originalConstraints = $constraints;
                    $constraints = function ($query) use ($columns, $originalConstraints) {
                        $query->select($columns);
                        $originalConstraints($query);
                    };
                } else {
                    $constraints = function ($query) use ($columns) {
                        $query->select($columns);
                    };
                }
            }

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Create a constraint to select only certain columns.
     *
     * @param string $name
     * @return array
     */
    protected function createSelectWithConstraint(string $name): array
    {
        [$name, $columns] = explode(':', $name);

        // Split columns by comma and trim whitespace
        $columns = array_map('trim', explode(',', $columns));

        return [$name, $columns];
    }

    /**
     * Get the models without eager loading.
     */
    public function getModels(array $columns = ['*']): array
    {
        return $this->model->hydrate(
            $this->query->get($columns),
            $this->model
        );
    }

    /**
     * Eager load the relationships for the models.
     */
    public function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            // Handle both simple and nested relations
            if (str_contains($name, '.')) {
                $models = $this->eagerLoadNestedRelation($models, $name, $constraints);
            } else {
                $models = $this->eagerLoadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eagerly load a nested relationship.
     */
    protected function eagerLoadNestedRelation(array $models, string $name, ?callable $constraints): array
    {
        // Split the nested relation into segments (e.g., "first.inner" => ["first", "inner"])
        $segments = explode('.', $name);
        $firstSegment = array_shift($segments);
        $remaining = implode('.', $segments);

        // Load the first level relation if not already loaded
        if (!isset($this->eagerLoad[$firstSegment])) {
            $models = $this->eagerLoadRelation($models, $firstSegment, null);
        }

        // Collect all related models from the first segment
        $relatedModels = [];
        foreach ($models as $model) {
            // Access relations through getRelations() method
            $relations = $model->getRelations();

            if (isset($relations[$firstSegment])) {
                $relation = $relations[$firstSegment];

                if ($relation instanceof Collection) {
                    foreach ($relation as $relatedModel) {
                        $relatedModels[] = $relatedModel;
                    }
                } elseif ($relation instanceof Model) {
                    $relatedModels[] = $relation;
                }
            }
        }

        // If we have related models, eager load the next level
        if (!empty($relatedModels)) {
            // Eager load recursively using the same pattern as the parent
            $this->eagerLoadRelationOnModels($relatedModels, $remaining, $constraints);
        }

        return $models;
    }

    /**
     * Helper method to eager load a relation on a set of models.
     */
    protected function eagerLoadRelationOnModels(array $models, string $name, ?callable $constraints): void
    {
        if (empty($models)) {
            return;
        }

        // Get the class of the first model
        $modelClass = get_class($models[0]);

        // Create a temporary instance to get the relation
        $tempInstance = new $modelClass;

        // Get the relation instance using Relation::noConstraints
        $relation = Relation::noConstraints(function () use ($tempInstance, $name) {
            return $tempInstance->$name();
        });

        // Add eager constraints for all models
        $relation->addEagerConstraints($models);

        // Apply custom constraints if provided
        if ($constraints) {
            $constraints($relation);
        }

        // Get the eager results
        $results = $relation->getEager();

        // Initialize relations on all models (set to null by default)
        $relation->initRelation($models, $name);

        // Match the results back to the models
        $relation->match($models, $results, $name);
    }

    /**
     * Eagerly load the relationship on a set of models.
     */
    protected function eagerLoadRelation(array $models, string $name, ?callable $constraints): array
    {
        // First check if models array is empty
        if (empty($models)) {
            return $models;
        }

        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        if ($constraints) {
            $constraints($relation);
        }

        return $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(),
            $name
        );
    }

    /**
     * Get the relation instance for the given relation name.
     */
    public function getRelation(string $name): Relation
    {
        // Try to call the method and check if it returns a Relation
        try {
            $result = Relation::noConstraints(function () use ($name) {
                return $this->getModel()->$name();
            });

            // If the result is not a Relation instance, it means the method doesn't exist
            // or doesn't return a relationship (likely forwarded to query builder via __call)
            if (!$result instanceof Relation) {
                throw new Exception(sprintf(
                    'Call to undefined relationship [%s] on model [%s].',
                    $name,
                    get_class($this->getModel())
                ));
            }

            return $result;
        } catch (\Throwable $e) {
            // Re-throw if it's already our exception
            if ($e instanceof Exception && str_contains($e->getMessage(), 'undefined relationship')) {
                throw $e;
            }

            // Otherwise, wrap it
            throw new Exception(sprintf(
                'Call to undefined relationship [%s] on model [%s].',
                $name,
                get_class($this->getModel())
            ));
        }
    }

    // ============================================
    // QUERY EXECUTION METHODS
    // ============================================

    public function get(array $columns = ['*']): Collection
    {
        // Get the base models
        $models = $this->getModels($columns);

        // If we have eager load relations, load them
        if (count($models) > 0 && !empty($this->eagerLoad)) {
            $models = $this->eagerLoadRelations($models);
        }

        return new Collection($models);
    }

    public function first(array $columns = ['*']): ?Model
    {
        $results = $this->limit(1)->get($columns);
        return $results->first();
    }

    public function firstOrFail(array $columns = ['*']): Model
    {
        $result = $this->first($columns);

        if ($result === null) {
            $tableName = $this->model->getTable();
            $primaryKey = $this->model->getKeyName();

            $value = null;
            $wheres = $this->query->getWheres();
            if (!empty($wheres)) {
                $lastWhere = end($wheres);
                $value = $lastWhere['value'] ?? json_encode($lastWhere['values'] ?? []);
            }

            throw new \RuntimeException(
                "No query results for query table [{$tableName}] with [{$primaryKey}] value [{$value}]"
            );
        }

        return $result;
    }

    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        if (is_array($id)) {
            throw new \InvalidArgumentException("Use findMany() when passing multiple IDs, or use whereIn().");
        }

        return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
    }


    /**
     * Find multiple models by their primary keys.
     */
    public function findMany(array $ids, array $columns = ['*']): Collection
    {
        if (empty($ids)) {
            return new Collection([]);
        }

        return $this->whereIn($this->model->getKeyName(), $ids)->get($columns);
    }

    public function findOrFail(mixed $id, array $columns = ['*']): Model|Collection
    {
        $result = $this->find($id, $columns);

        if ($result === null || ($result instanceof Collection && $result->isEmpty())) {
            $tableName = $this->model->getTable();
            $primaryKey = $this->model->getKeyName();
            $idValue = is_array($id) ? implode(', ', $id) : $id;

            throw new \RuntimeException(
                "No query results for table [{$tableName}] with {$primaryKey} value [{$idValue}]"
            );
        }

        return $result;
    }

    // ============================================
    // AGGREGATE METHODS
    // ============================================

    /**
     * Get the count of the total records.
     */
    public function count(string $columns = '*'): int
    {
        return $this->query->count($columns);
    }

    /**
     * Retrieve the maximum value of a column.
     */
    public function max(string $column): mixed
    {
        return $this->query->max($column);
    }

    /**
     * Retrieve the minimum value of a column.
     */
    public function min(string $column): mixed
    {
        return $this->query->min($column);
    }

    /**
     * Retrieve the sum of the values of a column.
     */
    public function sum(string $column): mixed
    {
        return $this->query->sum($column);
    }

    /**
     * Retrieve the average of the values of a column.
     */
    public function avg(string $column): mixed
    {
        return $this->query->avg($column);
    }

    /**
     * Determine if any rows exist for the current query.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Determine if no rows exist for the current query.
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    // ============================================
    // INSERT/UPDATE/DELETE METHODS
    // ============================================

    public function insert(array $values): bool
    {
        return $this->query->insert($values);
    }

    public function insertGetId(array $values): int
    {
        return $this->query->insertGetId($values);
    }

    public function insertIgnore(array $values): bool
    {
        return $this->query->insertIgnore($values);
    }

    public function update(array $values): int
    {
        return $this->query->update($this->addUpdatedAtColumn($values));
    }

    /**
     * Add the "updated at" column to an array of values.
     */
    protected function addUpdatedAtColumn(array $values): array
    {
        if (!$this->model->usesTimestamps()) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        if (!array_key_exists($column, $values)) {
            $values[$column] = $this->model->freshTimestampString();
        }

        return $values;
    }

    /**
     * Increment a column's value by a given amount.
     */
    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        if (!empty($extra)) {
            $extra = $this->addUpdatedAtColumn($extra);
        }

        return $this->query->increment($column, $amount, $extra);
    }

    /**
     * Decrement a column's value by a given amount.
     */
    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        if (!empty($extra)) {
            $extra = $this->addUpdatedAtColumn($extra);
        }

        return $this->query->decrement($column, $amount, $extra);
    }

    public function delete(): int
    {
        return $this->query->delete();
    }

    /**
     * Delete the model from the database with force delete (bypass soft deletes).
     */
    public function forceDelete(): int
    {
        return $this->query->delete();
    }

    public function truncate(): bool
    {
        return $this->query->truncate();
    }

    // ============================================
    // WHERE METHODS (delegated to QueryBuilder)
    // ============================================

    public function where(string|array|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->query->where($key, '=', $val);
            }
        } elseif ($column instanceof \Closure) {
            $this->query->where($column);
        } else {
            $this->query->where($column, $operator, $value);
        }

        return $this;
    }

    /**
     * Add a relationship count / exists condition to the query with where clauses.
     *
     * @param  string  $relation
     * @param  callable|null  $callback
     * @param  string  $operator
     * @param  int  $count
     * @return $this
     */
    public function whereHas(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
    {
        return $this->has($relation, $operator, $count, 'and', $callback);
    }


    public function orWhere(string|\Closure|array $column, mixed $operator = null, mixed $value = null): self
    {
        if ($column instanceof \Closure) {
            $this->query->orWhere($column);
        } elseif (is_array($column)) {
            // For array in orWhere, we need to group them
            $this->query->orWhere($column);
        } else {
            $this->query->orWhere($column, $operator, $value);
        }

        return $this;
    }

    /**
     * Add a relationship count / exists condition to the query with where clauses and an "or".
     *
     * @param string $relation
     * @param \Closure|null $callback
     * @param string $operator
     * @param int $count
     * @return $this
     */
    public function orWhereHas(string $relation, ?\Closure $callback = null, string $operator = '>=', int $count = 1): static
    {
        return $this->has($relation, $operator, $count, 'or', $callback);
    }

    /**
     * Add a relationship count / exists condition to the query with where clauses.
     *
     * @param string $relation
     * @param \Closure|null $callback
     * @return $this
     */
    public function whereDoesntHave(string $relation, ?\Closure $callback = null): static
    {
        return $this->doesntHave($relation, 'and', $callback);
    }

    /**
     * Add a relationship count / exists condition to the query with where clauses and an "or".
     *
     * @param string $relation
     * @param \Closure|null $callback
     * @return $this
     */
    public function orWhereDoesntHave(string $relation, ?\Closure $callback = null): static
    {
        return $this->doesntHave($relation, 'or', $callback);
    }

    /**
     * Add a relationship count / exists condition to the query.
     *
     * @param string $relation
     * @param string $boolean
     * @param \Closure|null $callback
     * @return $this
     */
    public function doesntHave(string $relation, string $boolean = 'and', ?\Closure $callback = null): static
    {
        return $this->has($relation, '<', 1, $boolean, $callback);
    }

    public function whereIn(string $column, mixed $values): self
    {
        if ($values instanceof \Closure) {
            // Handle subquery
            $subQuery = new Query($this->query->getConnection(), $this->model);
            $values($subQuery);

            // Build the IN clause with subquery
            $this->query->whereRaw(
                $this->query->getGrammar()->wrap($column) . " IN (" . $subQuery->toSql() . ")",
                $subQuery->getBindings()
            );

            return $this;
        }

        // Original array handling (ensure $values is array)
        if (!is_array($values)) {
            throw new \InvalidArgumentException("whereIn expects an array or Closure for values.");
        }

        $this->query->whereIn($column, $values);
        return $this;
    }


    public function whereNotIn(string $column, array $values): self
    {
        if ($values instanceof \Closure) {
            // Handle subquery
            $subQuery = new Query($this->query->getConnection(), $this->model);
            $values($subQuery);

            // Build the IN clause with subquery
            $this->query->whereRaw(
                $this->query->getGrammar()->wrap($column) . "NOT IN (" . $subQuery->toSql() . ")",
                $subQuery->getBindings()
            );

            return $this;
        }

        if (!is_array($values)) {
            throw new \InvalidArgumentException("whereNotIn expects an array or Closure for values.");
        }

        $this->query->whereNotIn($column, $values);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->query->whereNull($column);
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->query->whereNotNull($column);
        return $this;
    }

    public function whereBetween(string $column, array $values): self
    {
        $this->query->whereBetween($column, $values);
        return $this;
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $this->query->whereNotBetween($column, $min, $max);
        return $this;
    }

    public function whereColumn(string $first, ?string $operator = null, ?string $second = null): self
    {
        $this->query->whereColumn($first, $operator, $second);
        return $this;
    }

    public function whereLike(string $column, string $value): self
    {
        $this->query->whereLike($column, $value);
        return $this;
    }

    public function whereNotLike(string $column, string $value): self
    {
        $this->query->whereNotLike($column, $value);
        return $this;
    }

    public function whereDate(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->whereDate($column, $operator, $value);
        return $this;
    }

    public function whereYear(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->whereYear($column, $operator, $value);
        return $this;
    }

    public function whereMonth(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->whereMonth($column, $operator, $value);
        return $this;
    }

    public function whereDay(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->whereDay($column, $operator, $value);
        return $this;
    }

    public function whereTime(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->whereTime($column, $operator, $value);
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->query->whereRaw($sql, $bindings);
        return $this;
    }

    public function whereAny(array $columns, mixed $operator = null, mixed $value = null): self
    {
        $this->query->whereAny($columns, $operator, $value);
        return $this;
    }

    public function whereAll(array $columns, mixed $operator = null, mixed $value = null): self
    {
        $this->query->whereAll($columns, $operator, $value);
        return $this;
    }


    /**
     * Add a basic where clause to a relationship query.
     *
     * @param  string  $relation
     * @param  string|array|\Closure  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function whereRelation(string $relation, string|array|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->whereHas($relation, function ($query) use ($column, $operator, $value) {
            if ($column instanceof \Closure) {
                $column($query);
            } elseif (is_array($column)) {
                $query->where($column);
            } else {
                $query->where($column, $operator, $value);
            }
        });
    }

    /**
     * Add an "or where" clause to a relationship query.
     *
     * @param  string  $relation
     * @param  string|array|\Closure  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function orWhereRelation(string $relation, string|array|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->orWhereHas($relation, function ($query) use ($column, $operator, $value) {
            if ($column instanceof \Closure) {
                $column($query);
            } elseif (is_array($column)) {
                $query->where($column);
            } else {
                $query->where($column, $operator, $value);
            }
        });
    }

    /**
     * Add a relationship count / exists condition to the query with where clauses.
     *
     * @param  string  $relation
     * @param  string|array|\Closure  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function whereMorphRelation(string $relation, string|array $types, string|array|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->whereHasMorph($relation, $types, function ($query) use ($column, $operator, $value) {
            if ($column instanceof \Closure) {
                $column($query);
            } elseif (is_array($column)) {
                $query->where($column);
            } else {
                $query->where($column, $operator, $value);
            }
        });
    }

    /**
     * Add a relationship count / exists condition to the query with where clauses and an "or".
     *
     * @param  string  $relation
     * @param  string|array|\Closure  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function orWhereMorphRelation(string $relation, string|array $types, string|array|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->orWhereHasMorph($relation, $types, function ($query) use ($column, $operator, $value) {
            if ($column instanceof \Closure) {
                $column($query);
            } elseif (is_array($column)) {
                $query->where($column);
            } else {
                $query->where($column, $operator, $value);
            }
        });
    }

    // ============================================
    // JOIN METHODS
    // ============================================

    public function join(string $table, ?string $first = null, ?string $operator = null, ?string $second = null, string $type = 'inner'): self
    {
        $this->query->join($table, $first, $operator, $second, $type);
        return $this;
    }

    public function leftJoin(string $table, ?string $first = null, ?string $operator = null, ?string $second = null): self
    {
        $this->query->leftJoin($table, $first, $operator, $second);
        return $this;
    }

    public function rightJoin(string $table, ?string $first = null, ?string $operator = null, ?string $second = null): self
    {
        $this->query->rightJoin($table, $first, $operator, $second);
        return $this;
    }

    public function crossJoin(string $table): self
    {
        $this->query->crossJoin($table);
        return $this;
    }

    // ============================================
    // ORDER/GROUPING/LIMIT METHODS
    // ============================================

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function latest(?string $column = null): self
    {
        $column = $column ?? $this->model->getCreatedAtColumn();
        $this->query->latest($column);
        return $this;
    }

    public function oldest(?string $column = null): self
    {
        $column = $column ?? $this->model->getCreatedAtColumn();
        $this->query->oldest($column);
        return $this;
    }

    public function inRandomOrder(): self
    {
        $this->query->inRandomOrder();
        return $this;
    }

    public function groupBy(string|array ...$groups): self
    {
        $this->query->groupBy(...$groups);
        return $this;
    }

    public function having(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->having($column, $operator, $value);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->query->limit($limit);
        return $this;
    }

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function offset(int $offset): self
    {
        $this->query->offset($offset);
        return $this;
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->query->select($columns);
        return $this;
    }

    public function addSelect(array|string $columns): self
    {
        $this->query->addSelect($columns);
        return $this;
    }

    public function distinct(): self
    {
        $this->query->distinct();
        return $this;
    }

    // ============================================
    // UTILITY METHODS
    // ============================================


    /**
     * Add a relationship count / exists condition to the query.
     *
     * @param  string  $relation
     * @param  string  $operator
     * @param  int  $count
     * @param  string  $boolean
     * @param  callable|null  $callback
     * @return $this
     */
    public function has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', ?callable $callback = null): self
    {
        if (str_contains($relation, '.')) {
            return $this->hasNested($relation, $operator, $count, $boolean, $callback);
        }

        // Get the relation instance
        $relationInstance = $this->getRelationWithoutConstraints($relation);

        // Create a fresh query builder for the related model's table
        $relatedModel = $relationInstance->getRelated();
        $relatedQuery = \Maharlika\Database\Capsule::table($relatedModel->getTable());

        // Apply callback constraints if provided
        if ($callback) {
            $tempBuilder = new Builder($relatedQuery, $relatedModel);
            $callback($tempBuilder);
            $relatedQuery = $tempBuilder->getQuery();
        }

        // Get the existence query with proper joins and conditions
        $existenceQuery = $relationInstance->getRelationExistenceQuery(
            $relatedQuery,
            $this->query
        );

        // Build the EXISTS/COUNT clause properly
        $sql = $existenceQuery->toSql();
        $bindings = $existenceQuery->getBindings();

        // Handle count-based queries
        if ($count === 1 && $operator === '>=') {
            $this->query->whereRaw("EXISTS ({$sql})", $bindings);
        } elseif ($count === 1 && $operator === '<') {
            $this->query->whereRaw("NOT EXISTS ({$sql})", $bindings);
        } else {
            // For other counts, keep the derived table but ensure $sql is non-correlated
            $this->query->whereRaw("(SELECT COUNT(*) FROM ({$sql}) as aggregate_table) {$operator} ?", array_merge($bindings, [$count]));
        }

        return $this;
    }



    /**
     * Add nested relationship count / exists condition to the query.
     *
     * @param  string  $relations
     * @param  string  $operator
     * @param  int  $count
     * @param  string  $boolean
     * @param  callable|null  $callback
     * @return $this
     */
    protected function hasNested(string $relations, string $operator = '>=', int $count = 1, string $boolean = 'and', ?callable $callback = null): self
    {
        $relations = explode('.', $relations);
        $lastRelation = array_pop($relations);

        // Build nested closure working from the deepest relation up
        $nestedCallback = $callback;

        // Start with the last relation
        $currentCallback = function ($query) use ($lastRelation, $operator, $count, $nestedCallback) {
            $query->has($lastRelation, $operator, $count, 'and', $nestedCallback);
        };

        // Work backwards through parent relations
        while (count($relations) > 0) {
            $relation = array_pop($relations);
            $previousCallback = $currentCallback;

            $currentCallback = function ($query) use ($relation, $previousCallback) {
                $query->whereHas($relation, $previousCallback);
            };
        }

        // Apply to the first relation
        $firstRelation = array_shift($relations) ?: $lastRelation;
        return $this->whereHas($firstRelation, $currentCallback);
    }

    /**
     * Get the relation instance without constraints.
     *
     * @param  string  $relation
     * @return \Maharlika\Database\Relations\Relation
     */
    protected function getRelationWithoutConstraints(string $relation): Relation
    {
        return Relation::noConstraints(function () use ($relation) {
            return $this->getModel()->$relation();
        });
    }


    /**
     * Get a single column's value from the first result.
     */
    public function value(string $column): mixed
    {
        $result = $this->first([$column]);

        return $result?->$column;
    }

    /**
     * Get an array with the values of a given column.
     */
    public function pluck(string $column, ?string $key = null): Collection
    {
        $results = $this->get([$column, $key]);

        return $results->pluck($column, $key);
    }

    /**
     * Paginate the query results
     */
    public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): \Maharlika\Pagination\Paginator
    {
        $perPage = $perPage ?? ($this->model->getPerPage() ?? 15);
        $page = $page ?? $this->getCurrentPage($pageName);
        $page = max(1, $page);

        // Clone query to get total count
        $total = (clone $this)->count();

        // Get results for current page
        $results = $this->forPage($page, $perPage)->get($columns);

        return new Paginator(
            $results,
            $total,
            $perPage,
            $page
        );
    }

    protected function getCurrentPage(string $pageName): int
    {
        $page = $_GET[$pageName] ?? $_POST[$pageName] ?? 1;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return 1;
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $count)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Execute a callback over each item while chunking.
     */
    public function each(callable $callback, int $count = 100): bool
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Set the limit and offset for a given page.
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    // ============================================
    // QUERY INFO METHODS
    // ============================================
    public function toSql(): string
    {
        return $this->query->toSql();
    }

    public function getBindings(): array
    {
        return $this->query->getBindings();
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Apply the given scope on the current builder instance.
     */
    public function scopes(array $scopes): self
    {
        foreach ($scopes as $scope => $parameters) {
            if (is_int($scope)) {
                [$scope, $parameters] = [$parameters, []];
            }

            $this->callScope(
                $scope,
                is_array($parameters) ? $parameters : [$parameters]
            );
        }

        return $this;
    }

    /**
     * Apply the given scope on the current builder instance.
     */
    protected function callScope(string $scope, array $parameters = []): self
    {
        $method = 'scope' . ucfirst($scope);

        if (method_exists($this->model, $method)) {
            $this->model->$method($this, ...$parameters);
        }

        return $this;
    }

    /**
     * Dynamically handle calls to the class.
     */
    public function __call(string $method, array $parameters)
    {
        // Check if it's a scope
        if (method_exists($this->model, 'scope' . ucfirst($method))) {
            return $this->callScope($method, $parameters);
        }

        // Forward to query builder
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}
