<?php

namespace Maharlika\Database\Query;

use Maharlika\Contracts\Database\ConnectionInterface;
use Maharlika\Contracts\Database\QueryBuilderInterface;
use Maharlika\Database\Collection;
use Maharlika\Support\Str;
use Maharlika\Database\Grammar\Grammar;
use Maharlika\Database\FluentORM\Model;
use Maharlika\Database\SchemaValidator;
use Maharlika\Support\Carbon;
use Maharlika\Support\Traits\Macroable;

class Builder implements QueryBuilderInterface
{
    protected ConnectionInterface $connection;
    protected Grammar $grammar;
    protected SchemaValidator $validator;
    protected ?string $table = null;
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $joins = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected string $primaryKey = 'id';
    protected ?Model $model = null;
    protected array $groups = [];
    protected array $havings = [];
    protected array $unions = [];
    protected bool $distinct = false;
    protected array $havingBindings = [];
    protected bool $withPivotTimestamps = false;
    protected bool $skipValidation = false;

    public function __construct(ConnectionInterface $connection, ?Model $model = null)
    {
        $this->connection = $connection;
        $this->grammar = new Grammar();
        $this->validator = new SchemaValidator($connection);
        $this->model = $model;

        if ($this->model) {
            $this->table = $this->model->getTable();
            $this->primaryKey = $this->model->getKeyName();
        }
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function setPrimaryKey(string $primaryKey): self
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    // ============================================
    // SELECT METHODS
    // ============================================

    public function select(array|string|\Maharlika\Database\RawExpression $columns = ['*']): self
    {
        if ($columns instanceof \Maharlika\Database\RawExpression) {
            $this->columns = [$columns];
        } else {
            $this->columns = is_array($columns) ? $columns : func_get_args();
        }
        return $this;
    }

    public function from(string $table): self
    {
        return $this->table($table);
    }


    public function addSelect(array|string $columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        if ($this->columns === ['*']) {
            $this->columns = $columns;
        } else {
            $this->columns = array_merge($this->columns, $columns);
        }

        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }


    // ============================================
    // JOIN METHODS
    // ============================================

    public function join(
        string $table,
        ?string $first = null,
        ?string $operator = null,
        ?string $second = null,
        string $type = 'inner'
    ): self {
        if ($first === null && $second === null) {
            $first = "{$this->table}.{$this->primaryKey}";
            $second = "{$table}." . $this->getSingular($this->table) . "_{$this->primaryKey}";
            $operator = '=';
        } elseif ($operator === null) {
            $operator = '=';
        }

        $this->joins[] = compact('type', 'table', 'first', 'operator', 'second');

        return $this;
    }

    public function leftJoin(
        string $table,
        ?string $first = null,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(
        string $table,
        ?string $first = null,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'type' => 'cross',
            'table' => $table,
            'first' => null,
            'operator' => null,
            'second' => null
        ];

        return $this;
    }

    protected function getSingular(string $table): string
    {
        return Str::singular($table);
    }

    // ============================================
    // WHERE METHODS
    // ============================================

    public function where(string|\Closure|array|\Maharlika\Database\RawExpression $column, mixed $operator = null, mixed $value = null): self
    {
        // Handle RawExpression
        if ($column instanceof \Maharlika\Database\RawExpression) {
            return $this->whereRaw($column->getValue());
        }

        // Handle closure for nested where groups
        if ($column instanceof \Closure) {
            return $this->whereNested($column);
        }

        // Handle array of conditions
        if (is_array($column)) {
            return $this->whereArray($column);
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }


    public function orWhere(string|\Closure|array|\Maharlika\Database\RawExpression $column, mixed $operator = null, mixed $value = null): self
    {
        // Handle RawExpression
        if ($column instanceof \Maharlika\Database\RawExpression) {
            return $this->orWhereRaw($column->getValue());
        }

        // Handle closure for nested where groups
        if ($column instanceof \Closure) {
            return $this->whereNested($column, 'or');
        }

        // Handle array of conditions
        if (is_array($column)) {
            return $this->whereArray($column, 'or');
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }


    protected function whereNested(\Closure $callback, string $boolean = 'and'): self
    {
        $query = $this->forNestedWhere();

        call_user_func($callback, $query);

        return $this->addNestedWhereQuery($query, $boolean);
    }

    public function forNestedWhere(): self
    {
        // Create a new instance without copying wheres/bindings
        $instance = new static($this->connection, $this->model);
        $instance->table = $this->table;
        $instance->primaryKey = $this->primaryKey;
        return $instance;
    }

    public function addNestedWhereQuery(self $query, string $boolean = 'and'): self
    {
        if (count($query->wheres)) {
            $this->wheres[] = [
                'type' => 'nested',
                'query' => $query,
                'boolean' => empty($this->wheres) ? '' : $boolean
            ];

            $this->bindings = array_merge($this->bindings, $query->getBindings());
        }

        return $this;
    }

    protected function whereArray(array $conditions, string $boolean = 'and'): self
    {
        foreach ($conditions as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                // Handle nested arrays like [['column', 'operator', 'value']]
                $column = $value[0];
                $operator = $value[1] ?? '=';
                $val = $value[2] ?? null;

                if ($val === null && $operator !== null) {
                    $val = $operator;
                    $operator = '=';
                }

                if ($boolean === 'or') {
                    $this->orWhere($column, $operator, $val);
                } else {
                    $this->where($column, $operator, $val);
                }
            } else {
                // Handle associative arrays like ['column' => 'value']
                if ($boolean === 'or') {
                    $this->orWhere($key, '=', $value);
                } else {
                    $this->where($key, '=', $value);
                }
            }
        }

        return $this;
    }

    public function whereColumn(string $first, ?string $operator = null, ?string $second = null): self
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'values' => $values,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        return $this;
    }

    public function whereBetween(string $column, array $values, string $boolean = 'and'): self
    {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException("whereBetween expects exactly two values.");
        }

        $this->wheres[] = [
            'type'    => 'between',
            'column'  => $column,
            'min'     => $values[0],
            'max'     => $values[1],
            'boolean' => empty($this->wheres) ? '' : $boolean,
        ];

        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];

        return $this;
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'not_between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $min;
        $this->bindings[] = $max;

        return $this;
    }

    public function whereLike(string $column, string $value): self
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'value' => $value,
            'not' => false,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereNotLike(string $column, string $value): self
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'value' => $value,
            'not' => true,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereDate(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'date',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereYear(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'year',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereMonth(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'month',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereDay(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'day',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereTime(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'time',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }


    /**
     * ==========================
     * orWhere methods 
     * ==========================
     */

    /**
     * Add an OR WHERE NULL clause
     * 
     * @param string $column Column name
     * @return self
     */
    public function orWhereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'or'
        ];

        return $this;
    }

    /**
     * Add an OR WHERE NOT NULL clause
     * 
     * @param string $column Column name
     * @return self
     */
    public function orWhereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'or'
        ];

        return $this;
    }

    /**
     * Add an OR WHERE IN clause
     * 
     * @param string $column Column name
     * @param array $values Array of values
     * @return self
     */
    public function orWhereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'or'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Add an OR WHERE NOT IN clause
     * 
     * @param string $column Column name
     * @param array $values Array of values
     * @return self
     */
    public function orWhereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'or'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Add an OR WHERE BETWEEN clause
     * 
     * @param string $column Column name
     * @param array $values Array with two values [min, max]
     * @return self
     */
    public function orWhereBetween(string $column, array $values): self
    {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException("orWhereBetween expects exactly two values.");
        }

        $this->wheres[] = [
            'type'    => 'between',
            'column'  => $column,
            'min'     => $values[0],
            'max'     => $values[1],
            'boolean' => 'or',
        ];

        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];

        return $this;
    }

    /**
     * Add an OR WHERE NOT BETWEEN clause
     * 
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return self
     */
    public function orWhereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'not_between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => 'or'
        ];

        $this->bindings[] = $min;
        $this->bindings[] = $max;

        return $this;
    }

    /**
     * Add an OR WHERE LIKE clause
     * 
     * @param string $column Column name
     * @param string $value Pattern to match
     * @return self
     */
    public function orWhereLike(string $column, string $value): self
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'value' => $value,
            'not' => false,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR WHERE NOT LIKE clause
     * 
     * @param string $column Column name
     * @param string $value Pattern to match
     * @return self
     */
    public function orWhereNotLike(string $column, string $value): self
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'value' => $value,
            'not' => true,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR WHERE column comparison clause
     * 
     * @param string $first First column
     * @param string|null $operator Comparison operator
     * @param string|null $second Second column
     * @return self
     */
    public function orWhereColumn(string $first, ?string $operator = null, ?string $second = null): self
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => 'or'
        ];

        return $this;
    }

    /**
     * Add an OR WHERE DATE clause
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value if operator is provided
     * @return self
     */
    public function orWhereDate(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'date',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR WHERE YEAR clause
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value if operator is provided
     * @return self
     */
    public function orWhereYear(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'year',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR WHERE MONTH clause
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value if operator is provided
     * @return self
     */
    public function orWhereMonth(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'month',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR WHERE DAY clause
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value if operator is provided
     * @return self
     */
    public function orWhereDay(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'day',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR WHERE TIME clause
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value if operator is provided
     * @return self
     */
    public function orWhereTime(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'time',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return string
     */
    public function freshTimestamp(): Carbon
    {
        return now();
    }

    /**
     * Get a fresh timestamp string.
     *
     * @return string
     */
    public function freshTimestampString(): string
    {
        return $this->freshTimestamp();
    }

    /**
     * Add timestamps to the pivot table
     */
    public function withTimestamps(bool $timestamps = true): self
    {
        // This is typically used in BelongsToMany relationships
        // Store it as a flag that can be accessed by the relationship
        $this->withPivotTimestamps = $timestamps;
        return $this;
    }

    public function shouldAddTimestamps(): bool
    {
        return $this->withPivotTimestamps;
    }

    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    public function whereAny(array $columns, mixed $operator = null, mixed $value = null): self
    {
        if (empty($columns)) {
            return $this;
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'any',
            'columns' => $columns,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        foreach ($columns as $column) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function orWhereAny(array $columns, mixed $operator = null, mixed $value = null): self
    {
        if (empty($columns)) {
            return $this;
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'any',
            'columns' => $columns,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        foreach ($columns as $column) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereAll(array $columns, mixed $operator = null, mixed $value = null): self
    {
        if (empty($columns)) {
            return $this;
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        foreach ($columns as $column) {
            $this->where($column, $operator, $value);
        }

        return $this;
    }

    public function whereAnyNull(array $columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'any_null',
            'columns' => $columns,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        return $this;
    }

    public function whereAnyNotNull(array $columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'any_not_null',
            'columns' => $columns,
            'boolean' => empty($this->wheres) ? '' : 'and'
        ];

        return $this;
    }

    // ============================================
    // GROUP BY & HAVING
    // ============================================

    public function groupBy(string|array ...$groups): self
    {
        foreach ($groups as $group) {
            if (is_array($group)) {
                $this->groups = array_merge($this->groups, $group);
            } else {
                $this->groups[] = $group;
            }
        }

        return $this;
    }

    public function having(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->havings) ? '' : 'and'
        ];

        $this->havingBindings[] = $value;

        return $this;
    }

    public function orHaving(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->havingBindings[] = $value;

        return $this;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getHavings(): array
    {
        return $this->havings;
    }

    // ============================================
    // ORDER BY METHODS
    // ============================================

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc'
        ];

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    public function inRandomOrder(): self
    {
        $this->orders[] = [
            'column' => 'RAND()',
            'direction' => ''
        ];

        return $this;
    }

    // ============================================
    // LIMIT & OFFSET
    // ============================================

    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    // ============================================
    // UNION
    // ============================================

    public function union(Builder $query, bool $all = false): self
    {
        $this->unions[] = [
            'query' => $query->toSql(),
            'all' => $all
        ];

        $this->bindings = array_merge($this->bindings, $query->getBindings());

        return $this;
    }

    public function unionAll(Builder $query): self
    {
        return $this->union($query, true);
    }

    // ============================================
    // EXECUTION METHODS
    // ============================================

    public function get(): Collection
    {
        $sql = $this->grammar->compileSelect($this);
        $results = $this->connection->select($sql, $this->getBindings());

        // Convert each array to stdClass object for object access
        $objects = array_map(function ($row) {
            return (object) $row;
        }, $results);

        return new Collection($objects);
    }


    public function first(): ?object
    {
        $this->limit(1);
        $results = $this->get();
        return $results->first() ?: null;
    }

    public function firstOrFail(): ?object
    {
        $result = $this->first();

        if ($result === null) {
            return null;
        }

        return $result;
    }

    public function find(mixed $id): ?object
    {
        $this->wheres = [];
        $this->bindings = [];

        return $this->where($this->primaryKey, $id)->first();
    }

    public function findOrFail(mixed $id): object
    {
        $result = $this->find($id);

        if ($result === null) {
            throw new \RuntimeException(
                "No query results for table [{$this->table}] with {$this->primaryKey} [{$id}]"
            );
        }

        return $result;
    }

    public function findOne(mixed $id)
    {
        return $this->find($id);
    }

    // ============================================
    // INSERT METHODS
    // ============================================

    public function insert(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $sql = $this->grammar->compileInsert($this, $data);
        $bindings = $this->grammar->getInsertBindings($data);

        if (!$this->skipValidation && $this->model) {
            try {
                $this->validator->validateInsert($this->model->getTable(), $data, $sql, $bindings);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $this->connection->insert($sql, $bindings);
    }

    public function insertGetId(array $data): int
    {
        if ($this->insert($data)) {
            return (int) $this->connection->lastInsertId();
        }

        return 0;
    }

    public function insertIgnore(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $sql = $this->grammar->compileInsertIgnore($this, $data);
        $bindings = $this->grammar->getInsertBindings($data);

        return $this->connection->insert($sql, $bindings);
    }

    // ============================================
    // UPDATE METHOD
    // ============================================

    public function update(array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        $sql = $this->grammar->compileUpdate($this, $data);

        $bindings = array_merge(
            array_values($data),
            $this->bindings
        );

        // Validate before update
        if (!$this->skipValidation && $this->model) {
            $this->validator->validateUpdate($this->model->getTable(), $data, $sql, $bindings);
        }

        return $this->connection->update($sql, $bindings);
    }

    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        if (empty($extra)) {
            $extra = [];
        }

        $wrapped = $this->grammar->wrap($column);
        $sql = $this->grammar->compileUpdate($this, $extra);

        $sql = str_replace(
            $this->grammar->wrap($column) . " = ?",
            "{$wrapped} = {$wrapped} + {$amount}",
            $sql
        );

        unset($extra[$column]);

        $bindings = array_merge(
            array_values($extra),
            $this->bindings
        );

        // Validate extra data before update
        if (!$this->skipValidation && $this->model && !empty($extra)) {
            $this->validator->validateUpdate($this->model->getTable(), $extra, $sql, $bindings);
        }

        return $this->connection->update($sql, $bindings);
    }

    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        if (empty($extra)) {
            $extra = [];
        }

        $wrapped = $this->grammar->wrap($column);
        $sql = $this->grammar->compileUpdate($this, $extra);

        $sql = str_replace(
            $this->grammar->wrap($column) . " = ?",
            "{$wrapped} = {$wrapped} - {$amount}",
            $sql
        );

        unset($extra[$column]);

        $bindings = array_merge(
            array_values($extra),
            $this->bindings
        );

        // Validate extra data before update
        if (!$this->skipValidation && $this->model && !empty($extra)) {
            $this->validator->validateUpdate($this->model->getTable(), $extra, $sql, $bindings);
        }

        return $this->connection->update($sql, $bindings);
    }

    // ============================================
    // DELETE METHOD
    // ============================================

    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this);

        return $this->connection->delete($sql, $this->bindings);
    }

    public function truncate(): bool
    {
        $sql = $this->grammar->compileTruncate($this);

        return $this->connection->statement($sql);
    }

    // ============================================
    // AGGREGATE METHODS
    // ============================================

    public function count(string $column = '*'): int
    {
        $originalColumns = $this->columns;
        $this->columns = ["COUNT({$column}) as count"];

        $result = $this->first();

        $this->columns = $originalColumns;

        // Changed from $result['count'] to $result->count (object property access)
        return (int) ($result->count ?? 0);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function sum(string $column): mixed
    {
        return $this->aggregate('SUM', $column);
    }

    public function avg(string $column): mixed
    {
        return $this->aggregate('AVG', $column);
    }

    public function aggregate(string $function, string $column): mixed
    {
        $originalColumns = $this->columns;
        $this->columns = ["{$function}({$column}) as aggregate"];

        $result = $this->first();

        $this->columns = $originalColumns;

        // Changed from $result['aggregate'] to $result->aggregate
        return $result->aggregate ?? null;
    }
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    // ============================================
    // UTILITY METHODS
    // ============================================

    /**
     * Get the grammar instance
     */
    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    /**
     * Add bindings to the query
     */
    public function addBinding(mixed $value, string $type = 'where'): self
    {
        if ($type === 'where') {
            $this->bindings[] = $value;
        } elseif ($type === 'having') {
            $this->havingBindings[] = $value;
        }

        return $this;
    }

    /**
     * Merge bindings from another query
     */
    public function mergeBindings(Builder $query): self
    {
        $this->bindings = array_merge($this->bindings, $query->getBindings());
        return $this;
    }

    /**
     * Add a where clause with OR boolean
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => 'or'
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * Get the schema validator instance
     */
    public function getValidator(): SchemaValidator
    {
        return $this->validator;
    }

    public function value(string $column): mixed
    {
        $result = $this->select([$column])->first();
        return $result?->$column ?? null;
    }

    public function pluck(string $value, ?string $key = null): Collection
    {
        $results = $this->select([$value])->get();

        if ($key === null) {
            $items = array_map(fn($row) => $row->$value ?? null, $results->toArray());
        } else {
            $items = [];
            foreach ($results->toArray() as $row) {
                $keyValue = $row->$key ?? null;
                $valueValue = $row->$value ?? null;
                $items[$keyValue] = $valueValue;
            }
        }

        return new Collection($items);
    }

    public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): \Maharlika\Pagination\Paginator
    {
        $perPage = $perPage ?? ($this->model?->getPerPage());
        $page = $page ?? $this->getCurrentPage($pageName);
        $page = max(1, $page);

        $countQuery = clone $this;
        $total = $countQuery->count();

        $originalColumns = $this->columns;
        $this->columns = $columns;

        $results = $this->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $this->columns = $originalColumns;

        return new \Maharlika\Pagination\Paginator(
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

    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->offset(($page - 1) * $count)->limit($count)->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($results->count() === $count);

        return true;
    }

    public function each(callable $callback): bool
    {
        return $this->chunk(100, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Get the underlying database connection
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get the last inserted ID
     */
    public function lastInsertId(): string|false
    {
        return $this->connection->lastInsertId();
    }

    public function getBindings(): array
    {
        return array_merge($this->bindings, $this->havingBindings);
    }

    // ============================================
    // SQL COMPILATION
    // ============================================

    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }

    // ============================================
    // MAGIC METHODS
    // ============================================

    public function __call(string $method, array $parameters): mixed
    {
        if (str_starts_with($method, 'where') && $method !== 'where' && $method !== 'whereIn') {
            return $this->dynamicWhere($method, $parameters, 'where');
        }

        if (str_starts_with($method, 'orWhere') && $method !== 'orWhere') {
            return $this->dynamicWhere($method, $parameters, 'orWhere');
        }

        throw new \BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()',
            static::class,
            $method
        ));
    }

    protected function dynamicWhere(string $method, array $parameters, string $type): self
    {
        $column = Str::snake(
            substr($method, $type === 'where' ? 5 : 7)
        );

        if (count($parameters) === 1) {
            $operator = '=';
            $value = $parameters[0];
        } elseif (count($parameters) === 2) {
            $operator = $parameters[0];
            $value = $parameters[1];
        } else {
            throw new \InvalidArgumentException("Invalid number of parameters for {$method}");
        }

        return $type === 'where'
            ? $this->where($column, $operator, $value)
            : $this->orWhere($column, $operator, $value);
    }

    // ============================================
    // GETTERS FOR GRAMMAR
    // ============================================

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function getWheres(): array
    {
        return $this->wheres;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getLimit(): ?int
    {
        return $this->limitValue;
    }

    public function getOffset(): ?int
    {
        return $this->offsetValue;
    }
}
