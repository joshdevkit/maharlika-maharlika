<?php

namespace Maharlika\Database\Relations;

use Maharlika\Database\FluentORM\Model;
use Maharlika\Database\Capsule;
use Maharlika\Database\Collection;
use Maharlika\Database\FluentORM\Builder;

class BelongsToMany extends Relation
{
    /**
     * The intermediate table for the relation.
     */
    protected string $table;

    /**
     * The foreign key of the parent model.
     */
    protected string $foreignPivotKey;

    /**
     * The associated key of the relation.
     */
    protected string $relatedPivotKey;

    /**
     * The parent key.
     */
    protected string $parentKey;

    /**
     * The related key.
     */
    protected string $relatedKey;

    protected bool $withTimestamps = false;

    /**
     * The pivot table columns to retrieve.
     */
    protected array $pivotColumns = [];

    /**
     * Create a new belongs to many relationship instance.
     */
    public function __construct(
        Builder $query,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        $this->performJoin();

        if (static::$constraints) {
            $this->addWhereConstraints();
        }
    }

    /**
     * Perform the join to the pivot table.
     */
    protected function performJoin(?Builder $query = null): void
    {
        $query = $query ?: $this->query;
        $baseTable = $this->related->getTable();
        $pivotTable = $this->table;

        $query->getQuery()->join(
            $pivotTable,
            "{$baseTable}.{$this->relatedKey}",
            '=',
            "{$pivotTable}.{$this->relatedPivotKey}"
        );
    }

    /**
     * Set the where clause for the relation query.
     */
    protected function addWhereConstraints(): void
    {
        $this->query->where(
            "{$this->table}.{$this->foreignPivotKey}",
            '=',
            $this->parent->getAttribute($this->parentKey)
        );
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            "{$this->table}.{$this->foreignPivotKey}",
            $this->getKeys($models, $this->parentKey)
        );
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection([]));
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);
        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, new Collection($dictionary[$key]));
            } else {
                /**
                 * Set empty collection if no related models found
                 */
                $model->setRelation($relation, new Collection([]));
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $key = $result->pivot->getAttribute($this->foreignPivotKey);

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults(): Collection
    {
        return $this->get();
    }

    /**
     * Execute the query and get the models with pivot data.
     */
    public function get(array $columns = ['*']): Collection
    {
        // FIRST: Set the select columns on the query
        $selectColumns = $this->shouldSelect($columns);
        $this->query->select($selectColumns);

        // THEN: Get the models (columns are already set, so don't pass any)
        $models = $this->query->getModels();

        $this->hydratePivotRelation($models);

        return new Collection($models);
    }

    /**
     * Get the select columns for the relation query.
     */
    protected function shouldSelect(array $columns = ['*']): array
    {
        if ($columns == ['*']) {
            $columns = ["{$this->related->getTable()}.*"];
        }

        return array_merge($columns, $this->aliasedPivotColumns());
    }

    /**
     * Get the pivot columns for the relation.
     */
    protected function aliasedPivotColumns(): array
    {
        $defaults = [
            $this->foreignPivotKey,
            $this->relatedPivotKey,
        ];

        $columns = array_merge($defaults, $this->pivotColumns);

        return array_map(function ($column) {
            return "{$this->table}.{$column} as pivot_{$column}";
        }, array_unique($columns));
    }

    /**
     * Hydrate the pivot table relationship on the models.
     */
    protected function hydratePivotRelation(array $models): void
    {
        foreach ($models as $model) {
            $pivot = $this->migratePivotAttributes($model);
            //set the relation to the model as pivot
            $model->setRelation('pivot', $pivot);
        }
    }

    /**
     * Migrate the pivot attributes to a Pivot model.
     */
    protected function migratePivotAttributes(Model $model): Pivot
    {
        $attributes = [];
        $modelAttributes = $model->getAttributes();

        foreach ($modelAttributes as $key => $value) {
            if (str_starts_with($key, 'pivot_')) {
                $attributes[substr($key, 6)] = $value;
            }
        }

        // Remove pivot attributes from the model's attributes array
        // Use the removeAttribute method or directly manipulate the attributes
        foreach (array_keys($attributes) as $pivotKey) {
            $model->removeAttribute('pivot_' . $pivotKey);
        }

        return new Pivot($attributes, $this->table);
    }

    /**
     * Specify additional pivot columns to retrieve.
     */
    public function withPivot(string|array $columns): self
    {
        $this->pivotColumns = array_merge(
            $this->pivotColumns,
            is_array($columns) ? $columns : func_get_args()
        );

        return $this;
    }

    public function withTimestamps(bool $timestamps = true): self
    {
        $this->withTimestamps = $timestamps;
        if ($timestamps) {
            $this->pivotColumns = array_merge($this->pivotColumns, ['created_at', 'updated_at']);
        }
        return $this;
    }


    /**
     * Create a new instance of the related model and attach it.
     */
    public function create(array $attributes = [], array $pivotAttributes = []): Model
    {
        $instance = $this->related->newInstance($attributes);

        $instance->save();

        // Attach the newly created model with pivot attributes
        $this->attach($instance->getKey(), $pivotAttributes);

        return $instance;
    }

    /**
     * Attach a model to the parent.
     */
    public function attach(mixed $id, array $attributes = []): void
    {
        $records = $this->formatAttachRecords(
            is_array($id) ? $id : [$id => $attributes]
        );
        Capsule::table($this->table)->insert($records);
    }


    /**
     * Format the attach records.
     */
    protected function formatAttachRecords(array $records): array
    {
        $formatted = [];
        $now = now();
        foreach ($records as $key => $value) {
            $record = array_merge(
                [
                    $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
                    $this->relatedPivotKey => $key,
                ],
                is_array($value) ? $value : []
            );
            if ($this->withTimestamps) {
                $record['created_at'] = $record['created_at'] ?? $now;
                $record['updated_at'] = $record['updated_at'] ?? $now;
            }
            $formatted[] = $record;
        }
        return $formatted;
    }


    /**
     * Detach models from the relationship.
     */
    public function detach(mixed $ids = null): int
    {
        $query = Capsule::table($this->table)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));

        if (!is_null($ids)) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Sync the intermediate tables with a list of IDs.
     */
    public function sync(array $ids): array
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        $current = $this->getCurrentIds();

        $records = $this->formatRecordsList($ids);

        $detach = array_diff($current, array_keys($records));

        if (count($detach) > 0) {
            $this->detach($detach);
            $changes['detached'] = $detach;
        }

        $changes = array_merge(
            $changes,
            $this->attachNew($records, $current)
        );

        return $changes;
    }

    /**
     * Get the current IDs from the pivot table.
     */
    protected function getCurrentIds(): array
    {
        $results = Capsule::table($this->table)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->pluck($this->relatedPivotKey);

        return $results instanceof Collection ? $results->all() : $results;
    }

    /**
     * Format the sync records.
     */
    protected function formatRecordsList(array $records): array
    {
        $formatted = [];

        foreach ($records as $key => $value) {
            if (is_numeric($key)) {
                $formatted[$value] = [];
            } else {
                $formatted[$key] = $value;
            }
        }

        return $formatted;
    }

    /**
     * Attach new records.
     */
    protected function attachNew(array $records, array $current): array
    {
        $changes = ['attached' => [], 'updated' => []];
        foreach ($records as $id => $attributes) {
            if (!in_array($id, $current)) {
                if ($this->withTimestamps) {
                    $attributes['created_at'] = $attributes['created_at'] ?? now();
                    $attributes['updated_at'] = $attributes['updated_at'] ?? now();
                }
                $this->attach($id, $attributes);
                $changes['attached'][] = $id;
            }
        }
        return $changes;
    }

    /**
     * Get the pivot table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    public function getRelationExistenceQuery(
        \Maharlika\Database\Query\Builder $query,
        \Maharlika\Database\Query\Builder $parentQuery
    ): \Maharlika\Database\Query\Builder {
        // Build the properly qualified column names
        $relatedTable = $this->related->getTable();
        $parentTable = $this->parent->getTable();

        // Add join with properly qualified columns
        $query->join(
            $this->table,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->table}.{$this->relatedPivotKey}"
        );

        // Add where clause to link pivot table to parent
        // Reference the parent table from the outer query
        $query->whereColumn(
            "{$this->table}.{$this->foreignPivotKey}",
            '=',
            "{$parentTable}.{$this->parentKey}"
        );
        return $query;
    }

    /**
     * Set the columns to be selected.
     *
     * @param array|string $columns
     * @return $this
     */
    public function select(array|string $columns): self
    {
        // Convert string to array if needed
        if (is_string($columns)) {
            $columns = func_get_args();
        }

        // Ensure we're not selecting '*' when specific columns are requested
        if ($columns !== ['*']) {
            // Always include the related key in the selection
            $relatedTable = $this->related->getTable();
            $qualifiedRelatedKey = "{$relatedTable}.{$this->relatedKey}";

            // Check if related key is already in the selection
            $hasRelatedKey = false;
            foreach ($columns as $column) {
                if (
                    $column === $this->relatedKey ||
                    $column === $qualifiedRelatedKey ||
                    str_ends_with($column, '.*')
                ) {
                    $hasRelatedKey = true;
                    break;
                }
            }

            if (!$hasRelatedKey) {
                $columns[] = $qualifiedRelatedKey;
            }
        }

        $this->query->select($columns);

        return $this;
    }
}